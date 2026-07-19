<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settings\Show as SettingsShow;
use App\Livewire\Admin\Telegram\Show as TelegramShow;
use App\Models\Role;
use App\Models\Setting;
use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TelegramBotTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());
        Auth::login($admin);

        return $admin;
    }

    public function test_connecting_bot_verifies_token_and_registers_webhook(): void
    {
        Http::fake([
            'api.telegram.org/bot*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 111, 'username' => 'ribbon_bot']]),
            'api.telegram.org/bot*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->actingAsAdmin();

        Livewire::test(SettingsShow::class)
            ->set('telegramBotToken', '123:ABC')
            ->call('connectTelegramBot')
            ->assertHasNoErrors();

        $setting = Setting::current();

        $this->assertSame('123:ABC', $setting->telegram_bot_token);
        $this->assertSame('ribbon_bot', $setting->telegram_bot_username);
        $this->assertNotNull($setting->telegram_webhook_secret);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/setWebhook')
            && $request['secret_token'] === $setting->telegram_webhook_secret);
    }

    public function test_connecting_bot_with_invalid_token_saves_nothing(): void
    {
        Http::fake([
            'api.telegram.org/bot*/getMe' => Http::response(['ok' => false], 401),
        ]);

        $this->actingAsAdmin();

        Livewire::test(SettingsShow::class)
            ->set('telegramBotToken', 'bad-token')
            ->call('connectTelegramBot')
            ->assertHasErrors('telegramBotToken');

        $this->assertNull(Setting::current()->telegram_bot_username);
    }

    public function test_webhook_rejects_request_without_valid_secret(): void
    {
        Setting::current()->update(['telegram_webhook_secret' => 'the-real-secret']);

        $response = $this->postJson('/telegram/webhook', [
            'message' => ['chat' => ['id' => 555], 'text' => 'hi', 'message_id' => 1, 'from' => []],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret']);

        $response->assertForbidden();
        $this->assertDatabaseCount('telegram_contacts', 0);
    }

    public function test_webhook_stores_contact_and_inbound_message(): void
    {
        Setting::current()->update(['telegram_webhook_secret' => 'the-real-secret']);

        $response = $this->postJson('/telegram/webhook', [
            'message' => [
                'message_id' => 42,
                'text' => 'Hello, do you have ribbons in stock?',
                'chat' => ['id' => 987654321],
                'from' => ['username' => 'buyer_uz', 'first_name' => 'Aziz', 'last_name' => 'K'],
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'the-real-secret']);

        $response->assertOk();

        $contact = TelegramContact::query()->where('chat_id', 987654321)->firstOrFail();

        $this->assertSame('buyer_uz', $contact->username);
        $this->assertSame('Aziz', $contact->first_name);
        $this->assertNotNull($contact->last_message_at);

        $this->assertDatabaseHas('telegram_messages', [
            'telegram_contact_id' => $contact->id,
            'direction' => 'in',
            'body' => 'Hello, do you have ribbons in stock?',
            'telegram_message_id' => 42,
        ]);
    }

    public function test_super_admin_can_reply_to_a_contact_from_the_inbox(): void
    {
        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 999]]),
        ]);

        Setting::current()->update(['telegram_bot_token' => '123:ABC']);
        $admin = $this->actingAsAdmin();

        $contact = TelegramContact::create(['chat_id' => 111222333, 'first_name' => 'Test', 'last_message_at' => now()]);
        TelegramMessage::create(['telegram_contact_id' => $contact->id, 'direction' => 'in', 'body' => 'Hi there']);

        Livewire::test(TelegramShow::class)
            ->call('selectContact', $contact->id)
            ->set('replyText', 'Thanks for reaching out!')
            ->call('send')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('telegram_messages', [
            'telegram_contact_id' => $contact->id,
            'direction' => 'out',
            'body' => 'Thanks for reaching out!',
            'telegram_message_id' => 999,
            'sent_by' => $admin->id,
        ]);
    }

    public function test_non_super_admin_cannot_access_inbox_or_analytics(): void
    {
        $admin = User::factory()->create();
        $role = Role::create(['type' => 'admin', 'name' => 'Moderator', 'slug' => 'moderator-tg', 'is_super_admin' => false]);
        $admin->roles()->attach($role);
        Auth::login($admin);

        $this->get(route('admin.telegram.show'))->assertForbidden();
        $this->get(route('admin.analytics.show'))->assertForbidden();
    }
}
