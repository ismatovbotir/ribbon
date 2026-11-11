<?php

namespace Tests\Feature;

use App\Models\BotAnswer;
use App\Models\BotUnanswered;
use App\Models\Setting;
use App\Models\TelegramLink;
use App\Models\TelegramMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramRuleBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);

        Setting::current()->update([
            'telegram_bot_token' => 'bot-token',
            'telegram_bot_username' => 'ribbon_bot',
            'telegram_webhook_secret' => 'the-secret',
            'bot_telegram_enabled' => true,
        ]);

        BotAnswer::create([
            'name' => 'Prices',
            'reply' => array_fill_keys(config('ribbon.locales'), 'Prices are per unit.'),
            'keywords' => ['ru' => ['цена'], 'en' => ['price']],
            'buttons' => [['action' => 'url', 'target' => 'https://ribbon.uz/catalog', 'label' => array_fill_keys(config('ribbon.locales'), 'Catalog')]],
        ]);
    }

    private function webhook(array $update)
    {
        return $this->postJson('/telegram/webhook', $update, ['X-Telegram-Bot-Api-Secret-Token' => 'the-secret'])->assertOk();
    }

    private function text(string $text, int $chatId = 555, string $type = 'private', string $lang = 'ru'): array
    {
        return ['message' => [
            'message_id' => 1,
            'text' => $text,
            'chat' => ['id' => $chatId, 'type' => $type],
            'from' => ['id' => $chatId, 'language_code' => $lang],
        ]];
    }

    private function press(string $data, int $fromId = 555, ?int $chatId = null): array
    {
        return ['callback_query' => [
            'id' => 'cb1',
            'from' => ['id' => $fromId, 'language_code' => 'ru'],
            'data' => $data,
            'message' => ['message_id' => 77, 'chat' => ['id' => $chatId ?? $fromId, 'type' => 'private']],
        ]];
    }

    /** @return list<HttpRequest> */
    private function sent(string $chatId = '555'): array
    {
        return collect(Http::recorded())->map(fn ($pair) => $pair[0])
            ->filter(fn (HttpRequest $r) => str_ends_with($r->url(), '/sendMessage') && (string) $r['chat_id'] === $chatId)
            ->values()->all();
    }

    private function keyboard(HttpRequest $request): array
    {
        return collect($request['reply_markup']['inline_keyboard'] ?? [])->flatten(1)->all();
    }

    public function test_start_shows_the_menu_with_tap_buttons_in_the_buyers_language(): void
    {
        $this->webhook($this->text('/start', lang: 'uz'));

        $message = $this->sent()[0];
        $this->assertSame(__('storefront.bot.start_default', [], 'uz'), $message['text']);
        $this->assertSame(['b|finder|', 'b|handoff|'], array_column($this->keyboard($message), 'callback_data'));
        $this->assertSame(0, TelegramMessage::count());
    }

    public function test_a_question_gets_the_taught_answer_with_link_buttons_and_is_not_put_in_the_inbox(): void
    {
        $this->webhook($this->text('какая цена?'));

        $message = $this->sent()[0];
        $this->assertSame('Prices are per unit.', $message['text']);
        $this->assertSame('https://ribbon.uz/catalog', $this->keyboard($message)[0]['url']);
        $this->assertNull($message['parse_mode'] ?? null);
        $this->assertSame(0, TelegramMessage::count());
    }

    public function test_an_unknown_question_is_saved_for_staff_as_a_telegram_question(): void
    {
        $this->webhook($this->text('есть ли гарантия'));

        $this->assertSame('telegram', BotUnanswered::sole()->channel);
        $this->assertSame(__('storefront.bot.fallback', [], 'ru'), $this->sent()[0]['text']);
    }

    public function test_talk_to_a_person_hands_the_next_messages_to_the_admin_inbox_with_one_confirmation(): void
    {
        $this->webhook($this->press('b|handoff|'));
        $this->assertSame(__('storefront.bot.telegram_handoff', [], 'ru'), $this->sent()[0]['text']);

        $this->webhook($this->text('нужна гарантия на принтер'));
        $this->webhook($this->text('и доставка в Самарканд'));

        $this->assertSame(['нужна гарантия на принтер', 'и доставка в Самарканд'], TelegramMessage::orderBy('id')->pluck('body')->all());
        $this->assertCount(2, $this->sent());
        $this->assertSame(__('storefront.bot.telegram_sent', [], 'ru'), $this->sent()[1]['text']);
        $this->assertSame(0, BotUnanswered::count());
    }

    public function test_menu_command_brings_the_bot_back_from_talking_to_a_person(): void
    {
        $this->webhook($this->press('b|handoff|'));
        $this->webhook($this->text('/menu'));
        $this->webhook($this->text('цена'));

        $this->assertSame(0, TelegramMessage::count());
        $this->assertSame('Prices are per unit.', collect($this->sent())->last()['text']);
    }

    public function test_a_chat_that_floods_the_bot_is_ignored_after_the_limit(): void
    {
        foreach (range(1, 25) as $i) {
            $this->webhook($this->text("неизвестный вопрос номер {$i}"));
        }

        $this->assertCount(20, $this->sent());
        $this->assertSame(20, BotUnanswered::count());
        $this->assertSame(0, TelegramMessage::count());
    }

    public function test_a_badly_formed_link_goes_into_the_text_so_the_reply_still_arrives(): void
    {
        BotAnswer::where('name', 'Prices')->update(['buttons' => [['action' => 'url', 'target' => 'https://x', 'label' => array_fill_keys(config('ribbon.locales'), 'Bad')]]]);

        $this->webhook($this->text('цена'));

        $message = collect($this->sent())->last();
        $this->assertSame('Prices are per unit.', $message['text']);
        $this->assertNotContains('https://x', array_column($this->keyboard($message), 'url'));
    }

    public function test_a_button_press_runs_the_engine_and_stops_the_spinner(): void
    {
        $this->webhook($this->press('b|start|'));

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/answerCallbackQuery') && $r['callback_query_id'] === 'cb1');
        $this->assertSame(__('storefront.bot.start_default', [], 'ru'), $this->sent()[0]['text']);
    }

    public function test_a_press_from_someone_else_or_outside_a_private_chat_does_nothing(): void
    {
        $this->webhook($this->press('b|start|', fromId: 555, chatId: 999));

        $this->assertSame([], $this->sent('555'));
        $this->assertSame([], $this->sent('999'));
    }

    public function test_group_chats_linked_sellers_and_a_switched_off_bot_keep_the_old_behaviour(): void
    {
        $this->webhook($this->text('цена?', chatId: -100, type: 'group'));

        $seller = User::factory()->create();
        TelegramLink::create(['user_id' => $seller->id, 'chat_id' => '777']);
        $this->webhook($this->text('цена?', chatId: 777));

        Setting::current()->update(['bot_telegram_enabled' => false]);
        $this->webhook($this->text('цена?', chatId: 888));

        $this->assertSame(3, TelegramMessage::count());
        $this->assertSame([], $this->sent('-100'));
        $this->assertSame([], $this->sent('777'));
        $this->assertSame([], $this->sent('888'));
    }

    public function test_seller_outcome_buttons_still_go_to_the_seller_handler(): void
    {
        $this->webhook($this->press('ro:1:won'));

        // The seller handler answers (refusing an unknown request); the bot sends no message.
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/answerCallbackQuery') && $r['text'] === __('sellers.telegram.outcome_refused'));
        $this->assertSame([], $this->sent());
    }

    public function test_non_https_links_are_written_into_the_text_instead_of_a_button(): void
    {
        BotAnswer::create([
            'name' => 'Contact',
            'reply' => array_fill_keys(config('ribbon.locales'), 'Write to us.'),
            'keywords' => ['ru' => ['контакты']],
            'buttons' => [['action' => 'url', 'target' => '/contact', 'label' => array_fill_keys(config('ribbon.locales'), 'Contact page')]],
        ]);

        $this->webhook($this->text('контакты'));

        $message = $this->sent()[0];
        $expected = str_starts_with(url('/contact'), 'https://') ? 'Write to us.' : 'Write to us.'."\n\nContact page: ".url('/contact');
        $this->assertSame($expected, $message['text']);
    }
}
