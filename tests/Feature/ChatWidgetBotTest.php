<?php

namespace Tests\Feature;

use App\Livewire\Storefront\ChatWidget;
use App\Models\BotAnswer;
use App\Models\BotUnanswered;
use App\Models\ContactMessage;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class ChatWidgetBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('chat:ip:127.0.0.1');
        RateLimiter::clear('bot:ip:127.0.0.1');
        RateLimiter::clear('bot:press:127.0.0.1');
    }

    private function botOn(): void
    {
        Setting::current()->update(['bot_web_enabled' => true]);
        BotAnswer::create([
            'name' => 'Prices',
            'reply' => array_fill_keys(config('ribbon.locales'), 'Prices are per unit.'),
            'keywords' => ['en' => ['price'], 'ru' => ['цена'], 'uz' => ['narx']],
        ]);
    }

    public function test_with_the_bot_off_the_window_is_the_plain_message_form(): void
    {
        Livewire::test(ChatWidget::class)
            ->assertSet('botMode', false)
            ->assertSee(__('storefront.chat.intro'))
            ->assertDontSee('id="chat-text"', false)
            ->call('ask')
            ->assertSet('messages', []);
    }

    public function test_the_greeting_loads_on_first_open_only(): void
    {
        $this->botOn();

        $widget = Livewire::test(ChatWidget::class)->assertSet('botMode', true)->assertSet('messages', []);

        $widget->call('openBot')->call('openBot');
        $this->assertCount(1, $widget->get('messages'));
        $this->assertSame(__('storefront.bot.start_default'), $widget->get('messages')[0]['text']);
    }

    public function test_a_question_gets_the_taught_answer_and_an_unknown_one_is_saved_for_staff(): void
    {
        $this->botOn();

        $widget = Livewire::test(ChatWidget::class)
            ->set('text', 'narxi qancha?')->call('ask')
            ->assertSee('Prices are per unit.')
            ->set('text', 'kafolat bormi')->call('ask')
            ->assertSee(__('storefront.bot.fallback'));

        $this->assertSame(1, BotAnswer::sole()->hits);
        $this->assertSame('web', BotUnanswered::sole()->channel);
        $this->assertSame('', $widget->get('text'));
    }

    public function test_talk_to_a_person_opens_the_form_with_the_last_question_and_sends_it_to_staff(): void
    {
        $this->botOn();

        $widget = Livewire::test(ChatWidget::class)->set('text', 'kafolat bormi')->call('ask');
        $buttons = collect($widget->get('messages'))->last()['buttons'];
        $handoff = collect($buttons)->search(fn ($b) => $b['action'] === 'handoff');

        $widget->call('press', count($widget->get('messages')) - 1, $handoff)
            ->assertSet('formMode', 'handoff')
            ->assertSet('message', 'kafolat bormi')
            ->assertSee(__('storefront.chat.back'))
            ->set('phone', '+998901112233');

        $this->travel(5)->seconds();
        $widget->call('submit')->assertHasNoErrors()->assertSet('formMode', null);

        $message = ContactMessage::sole();
        $this->assertSame('chat', $message->source);
        $this->assertSame('kafolat bormi', $message->message);
    }

    public function test_the_conversation_survives_a_page_change_and_restarts_cleanly(): void
    {
        $this->botOn();

        Livewire::test(ChatWidget::class)->set('text', 'price')->call('ask');

        $next = Livewire::test(ChatWidget::class);
        $this->assertCount(2, $next->get('messages'));

        $next->call('restartBot');
        $this->assertCount(1, $next->get('messages'));
    }

    public function test_an_open_phone_form_is_still_open_on_the_next_page(): void
    {
        $this->botOn();

        $widget = Livewire::test(ChatWidget::class)->call('openBot');
        $handoff = collect($widget->get('messages')[0]['buttons'])->search(fn ($b) => $b['action'] === 'handoff');
        $widget->call('press', 0, $handoff)->assertSet('formMode', 'handoff');

        Livewire::test(ChatWidget::class)->assertSet('formMode', 'handoff')->call('backToBot');

        Livewire::test(ChatWidget::class)->assertSet('formMode', null);
    }

    public function test_the_transcript_and_form_state_cannot_be_forged(): void
    {
        $this->botOn();

        $widget = Livewire::test(ChatWidget::class)->call('openBot');
        $widget->call('press', 99, 0)->call('press', 0, 99);
        $this->assertCount(1, $widget->get('messages'));

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $widget->set('messages', [['from' => 'bot', 'text' => 'x', 'buttons' => [['label' => 'x', 'action' => 'answer', 'target' => '1', 'url' => null]]]]);
    }

    public function test_typed_questions_are_rate_limited_per_ip(): void
    {
        $this->botOn();
        $widget = Livewire::test(ChatWidget::class);

        foreach (range(1, 21) as $i) {
            $widget->set('text', "unknown question number {$i}")->call('ask');
        }

        $this->assertSame(__('storefront.chat.throttled'), collect($widget->get('messages'))->last()['text']);
        $this->assertSame(20, BotUnanswered::count());
    }

    public function test_a_spam_trapped_phone_form_looks_sent_in_bot_mode_and_stores_nothing(): void
    {
        $this->botOn();
        $widget = Livewire::test(ChatWidget::class)->call('openBot');
        $last = count($widget->get('messages')) - 1;
        $handoff = collect($widget->get('messages')[$last]['buttons'])->search(fn ($b) => $b['action'] === 'handoff');

        $widget->call('press', $last, $handoff)
            ->set('phone', '+998901234567')->set('message', 'Need ribbons')
            ->call('submit'); // within 3 seconds of rendering: caught by the time trap

        $this->assertSame(0, ContactMessage::count());
        $this->assertNull($widget->get('formMode'));
        $this->assertSame(__('storefront.chat.success'), collect($widget->get('messages'))->last()['text']);
    }

    public function test_the_phone_form_after_the_finder_carries_the_finder_choices(): void
    {
        $this->botOn();
        $widget = Livewire::test(ChatWidget::class);
        $messages = [
            ['from' => 'user', 'text' => 'Find a product', 'typed' => false, 'action' => 'finder', 'target' => ''],
            ['from' => 'user', 'text' => 'Ribbons', 'typed' => false, 'action' => 'finder', 'target' => 'c1'],
            ['from' => 'user', 'text' => '110 mm', 'typed' => false, 'action' => 'finder', 'target' => 'c1.2-3'],
            ['from' => 'bot', 'kind' => 'finder', 'text' => 'Pick', 'buttons' => [['label' => 'Leave my phone', 'action' => 'lead', 'target' => '', 'url' => null]]],
        ];
        session()->put('bot.web', ['locale' => app()->getLocale(), 'messages' => $messages, 'form' => null]);

        $widget = Livewire::test(ChatWidget::class)->call('press', 3, 0);

        $this->assertSame('lead', $widget->get('formMode'));
        $this->assertSame('Ribbons → 110 mm', $widget->get('message'));
    }

    public function test_button_presses_are_rate_limited_per_ip(): void
    {
        $this->botOn();
        $widget = Livewire::test(ChatWidget::class)->call('openBot');

        foreach (range(1, 61) as $i) {
            $last = count($widget->get('messages')) - 1;
            $button = collect($widget->get('messages')[$last]['buttons'])->search(fn ($b) => $b['action'] === 'menu');
            $widget->call('press', $last, $button === false ? 0 : $button);
        }

        $this->assertSame(__('storefront.chat.throttled'), collect($widget->get('messages'))->last()['text']);
    }
}
