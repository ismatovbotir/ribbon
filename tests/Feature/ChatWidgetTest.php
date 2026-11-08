<?php

namespace Tests\Feature;

use App\Livewire\Admin\ContactMessages\Index as ContactMessagesIndex;
use App\Livewire\Storefront\ChatWidget;
use App\Models\ContactMessage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schedule;
use Livewire\Livewire;
use Tests\TestCase;

class ChatWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('chat:ip:127.0.0.1');
    }

    private function send(string $phone = '+998901112233', string $message = 'Do you have 110mm resin?', array $extra = [])
    {
        $component = Livewire::test(ChatWidget::class)->set('phone', $phone)->set('message', $message);

        foreach ($extra as $key => $value) {
            $component->set($key, $value);
        }

        $this->travel(5)->seconds();

        return $component->call('submit');
    }

    private function bot(): void
    {
        Setting::current()->update(['telegram_bot_token' => 'bot-token']);
        config(['services.telegram.super_admin_chat_id' => '999']);
    }

    private function message(array $attributes = []): ContactMessage
    {
        return ContactMessage::create($attributes + [
            'source' => 'chat', 'phone' => '+998901112233', 'message' => 'Hello', 'locale' => 'uz', 'status' => 'new',
        ]);
    }

    // ---- Submitting

    public function test_a_message_is_stored_as_waiting_and_not_sent_straight_away(): void
    {
        Http::fake();

        $this->send()->assertHasNoErrors()->assertSet('submitted', true);

        $message = ContactMessage::sole();
        $this->assertSame('chat', $message->source);
        $this->assertSame('+998901112233', $message->phone);
        $this->assertSame('Do you have 110mm resin?', $message->message);
        $this->assertNull($message->telegram_sent_at);
        Http::assertNothingSent();
    }

    public function test_phone_and_message_are_required(): void
    {
        $this->send('', '')->assertHasErrors(['phone', 'message']);
        $this->send('abc', 'Hello')->assertHasErrors(['phone']);
        $this->send('+998901112233', str_repeat('x', 1001))->assertHasErrors(['message']);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_bots_get_a_fake_success_and_nothing_is_stored(): void
    {
        $this->send(extra: ['website' => 'http://spam.test'])->assertSet('submitted', true);

        Livewire::test(ChatWidget::class)
            ->set('phone', '+998901112233')
            ->set('message', 'Too fast')
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_repeat_messages_from_one_phone_are_limited(): void
    {
        foreach (range(1, 3) as $i) {
            $this->send()->assertHasNoErrors();
        }

        $this->send()->assertHasErrors(['message']);

        $this->assertSame(3, ContactMessage::count());
    }

    public function test_the_page_is_stored_without_its_query_string(): void
    {
        $this->withoutExceptionHandling();
        $this->get('/articles?phone=secret')->assertOk();

        $this->send()->assertHasNoErrors();

        $this->assertStringNotContainsString('secret', (string) ContactMessage::sole()->page);
    }

    // ---- Where it shows

    public function test_the_consultant_is_on_every_public_page(): void
    {
        foreach (['/', '/articles', route('storefront.contact'), route('storefront.offer-request.show'), '/sellers/register', '/login'] as $url) {
            $this->get($url)->assertOk()->assertSee(__('storefront.chat.open'));
        }
    }

    public function test_the_consultant_is_not_in_the_admin_panel(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        $this->actingAs($admin)->get('/admin/brands')->assertOk()->assertDontSee(__('storefront.chat.open'));
    }

    // ---- Sending on a schedule

    public function test_the_command_sends_waiting_messages_once(): void
    {
        $this->bot();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        $message = $this->message(['message' => 'Need <b>price</b>', 'page' => '/catalog']);

        $this->artisan('chat-messages:send')->assertSuccessful();
        $this->artisan('chat-messages:send')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $r) => (string) $r['chat_id'] === '999'
            && str_contains($r['text'], '+998901112233')
            && str_contains($r['text'], 'Need &lt;b&gt;price&lt;/b&gt;')
            && str_contains($r['text'], '/catalog'));
        $this->assertNotNull($message->fresh()->telegram_sent_at);
    }

    public function test_a_failed_send_is_retried_on_the_next_run_in_order(): void
    {
        $this->bot();
        Http::fake(['api.telegram.org/*' => Http::sequence()
            ->push(['ok' => false], 500)
            ->push(['ok' => true, 'result' => ['message_id' => 1]])
            ->push(['ok' => true, 'result' => ['message_id' => 2]])]);
        $first = $this->message();
        $second = $this->message();

        $this->artisan('chat-messages:send');
        Http::assertSentCount(1);
        $this->assertNull($first->fresh()->telegram_sent_at);
        $this->assertNull($second->fresh()->telegram_sent_at);

        $this->artisan('chat-messages:send');
        $this->assertNotNull($first->fresh()->telegram_sent_at);
        $this->assertNotNull($second->fresh()->telegram_sent_at);
    }

    public function test_without_a_bot_messages_keep_waiting(): void
    {
        Http::fake();
        config(['services.telegram.bot_token' => null, 'services.telegram.super_admin_chat_id' => null]);
        $message = $this->message();

        $this->artisan('chat-messages:send')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertNull($message->fresh()->telegram_sent_at);
    }

    public function test_other_contact_messages_are_not_sent_by_this_command(): void
    {
        $this->bot();
        Http::fake();
        $this->message(['source' => 'contact']);

        $this->artisan('chat-messages:send')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_the_command_is_scheduled_every_minute(): void
    {
        $event = collect(Schedule::events())->first(fn ($e) => str_contains((string) $e->command, 'chat-messages:send'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    // ---- Privacy

    public function test_super_admin_sees_chat_messages_and_a_moderator_cannot(): void
    {
        $this->message(['message' => 'Find me in admin']);

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        Livewire::actingAs($admin)->test(ContactMessagesIndex::class)
            ->assertSee('Find me in admin')
            ->assertSee(__('admin.cms.contact.source_chat'));

        $role = Role::create(['type' => 'admin', 'name' => 'Mod', 'slug' => 'mod-chat', 'is_super_admin' => false]);
        $role->permissions()->attach(Permission::firstOrCreate(['slug' => 'content.manage'], ['name' => 'content.manage']));
        $moderator = User::factory()->create();
        $moderator->roles()->attach($role);

        Livewire::actingAs($moderator)->test(ContactMessagesIndex::class)->assertForbidden();
    }
}
