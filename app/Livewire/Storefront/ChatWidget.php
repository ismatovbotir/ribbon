<?php

namespace App\Livewire\Storefront;

use App\Models\ContactMessage;
use App\Models\Setting;
use App\Rules\PhoneNumber;
use App\Services\Bot\BotEngine;
use App\Support\Attribution;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The logo button in the storefront corner ("Ribbon consultant").
 *
 * - Bot off (default): a form that stores a question with the visitor's
 *   phone; `chat-messages:send` delivers it to the Super Admin on Telegram on
 *   a schedule, so a Telegram outage never loses a message.
 * - Bot on (Admin > Bot training > Website consultant): the rule-based bot
 *   (no AI, App\Services\Bot\BotEngine) answers first. "Leave your phone" and
 *   "Talk to a person" open the same form, so nothing reaches staff any
 *   other way. The conversation lives in the visitor's session (no account,
 *   no database row) so it survives page changes; the transcript is #[Locked]
 *   and button presses are read from it, never from client input.
 */
class ChatWidget extends Component
{
    private const MIN_FILL_SECONDS = 3;

    private const SESSION_KEY = 'bot.web';

    private const MAX_MESSAGES = 30;

    /** Typed questions per IP per minute (keeps the Unanswered list clean). */
    private const ASKS_PER_MINUTE = 20;

    /** Button presses per IP per minute (each finder step runs product counts). */
    private const PRESSES_PER_MINUTE = 60;

    public string $phone = '';

    public string $message = '';

    public bool $submitted = false;

    /** Honeypot: real visitors never fill it. */
    public string $website = '';

    /** What the visitor is typing to the bot. */
    public string $text = '';

    #[Locked]
    public int $renderedAt = 0;

    /** @var array<string, string> */
    #[Locked]
    public array $utm = [];

    #[Locked]
    public ?string $referrer = null;

    /** Path only: a query string could carry personal data. */
    #[Locked]
    public string $page = '';

    #[Locked]
    public bool $botMode = false;

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $messages = [];

    /** null = chatting with the bot; 'lead' | 'handoff' = showing the phone form. */
    #[Locked]
    public ?string $formMode = null;

    public function mount(): void
    {
        $this->renderedAt = now()->getTimestamp();
        $this->page = '/'.ltrim(request()->path(), '/');

        ['utm' => $this->utm, 'referrer' => $this->referrer] = Attribution::current(request());

        $this->botMode = (bool) Setting::current()->bot_web_enabled;

        if ($this->botMode) {
            $saved = session(self::SESSION_KEY);
            $current = is_array($saved) && ($saved['locale'] ?? null) === app()->getLocale();
            $this->messages = $current ? (array) ($saved['messages'] ?? []) : [];
            $this->formMode = $current && in_array($saved['form'] ?? null, ['lead', 'handoff'], true) ? $saved['form'] : null;
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32', new PhoneNumber],
            'message' => ['required', 'string', 'min:2', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'phone' => __('storefront.chat.phone'),
            'message' => __('storefront.chat.message'),
        ];
    }

    /** First open: greet with the start menu. Called by the window, so plain page views cost nothing. */
    public function openBot(BotEngine $engine): void
    {
        if ($this->botMode && $this->messages === []) {
            $this->addBot($engine->start(app()->getLocale())->toArray());
        }
    }

    public function restartBot(BotEngine $engine): void
    {
        if (! $this->botMode) {
            return;
        }

        $this->messages = [];
        $this->formMode = null;
        $this->submitted = false;
        $this->addBot($engine->start(app()->getLocale())->toArray());
    }

    public function ask(BotEngine $engine): void
    {
        $text = trim(mb_substr($this->text, 0, 500));
        $this->text = '';

        if (! $this->botMode || $text === '') {
            return;
        }

        $key = 'bot:ip:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::ASKS_PER_MINUTE)) {
            $this->addBot(['kind' => 'fallback', 'text' => __('storefront.chat.throttled'), 'buttons' => []]);

            return;
        }

        RateLimiter::hit($key, 60);

        // `typed`: a real question (button labels are recorded as user turns too).
        $this->add(['from' => 'user', 'text' => $text, 'typed' => true]);
        $this->handle($engine->ask($text, app()->getLocale(), 'web')->toArray());
    }

    public function press(BotEngine $engine, int $message, int $button): void
    {
        $picked = $this->messages[$message]['buttons'][$button] ?? null;

        if (! $this->botMode || ! is_array($picked) || ! empty($picked['url'])) {
            return;
        }

        $key = 'bot:press:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::PRESSES_PER_MINUTE)) {
            $this->addBot(['kind' => 'fallback', 'text' => __('storefront.chat.throttled'), 'buttons' => []]);

            return;
        }

        RateLimiter::hit($key, 60);

        $this->add(['from' => 'user', 'text' => (string) $picked['label'], 'typed' => false, 'action' => (string) $picked['action'], 'target' => (string) $picked['target']]);
        $this->handle($engine->press((string) $picked['action'], (string) $picked['target'], app()->getLocale())->toArray());
    }

    /** Leave the phone form and go back to the conversation. */
    public function backToBot(): void
    {
        $this->formMode = null;
        $this->resetErrorBag();
        $this->remember();
    }

    private function handle(array $reply): void
    {
        $this->addBot($reply);

        if (in_array($reply['form'] ?? null, ['lead', 'handoff'], true)) {
            $this->formMode = $reply['form'];
            $this->submitted = false;
            $this->remember();

            // Carry what the visitor already told the bot into the form so they need not repeat it.
            if (trim($this->message) === '') {
                $this->message = $this->contextForForm();
            }
        }
    }

    /**
     * The last typed question, plus the product finder choices made after it
     * (e.g. "Ribbons → 110 mm → Wax"), so staff see what the buyer was looking for.
     */
    private function contextForForm(): string
    {
        $typed = '';
        $choices = [];

        foreach (array_reverse(array_filter($this->messages, fn ($m) => $m['from'] === 'user')) as $turn) {
            if (! empty($turn['typed'])) {
                $typed = (string) $turn['text'];
                break;
            }

            $action = $turn['action'] ?? null;

            if ($action === 'finder' && ($turn['target'] ?? '') !== '') {
                array_unshift($choices, (string) $turn['text']);
            } elseif ($choices !== []) {
                break; // the finder started here (or the visitor left it)
            }
        }

        return trim($typed."\n".implode(' → ', $choices));
    }

    private function addBot(array $reply): void
    {
        $this->add(['from' => 'bot'] + array_intersect_key($reply, array_flip(['kind', 'text', 'buttons', 'form'])));
    }

    private function add(array $message): void
    {
        $this->messages = array_slice([...$this->messages, $message], -self::MAX_MESSAGES);
        $this->remember();
    }

    /** Keep the conversation (and an open phone form) for the next page. */
    private function remember(): void
    {
        session()->put(self::SESSION_KEY, ['locale' => app()->getLocale(), 'messages' => $this->messages, 'form' => $this->formMode]);
    }

    public function submit(): void
    {
        // Spam traps first: pretend success so bots get no signal (nothing is stored).
        if ($this->website !== '' || now()->getTimestamp() - $this->renderedAt < self::MIN_FILL_SECONDS) {
            $this->sent();

            return;
        }

        $this->resetErrorBag();
        $this->validate();

        $ipKey = 'chat:ip:'.request()->ip();
        $phoneKey = 'chat:phone:'.PhoneNumber::digits($this->phone);

        if (RateLimiter::tooManyAttempts($ipKey, 5) || RateLimiter::tooManyAttempts($phoneKey, 3)) {
            $this->addError('message', __('storefront.chat.throttled'));

            return;
        }

        RateLimiter::hit($ipKey, 600);
        RateLimiter::hit($phoneKey, 3600);

        ContactMessage::create([
            'source' => 'chat',
            'phone' => trim($this->phone),
            'message' => trim($this->message),
            'page' => mb_substr($this->page, 0, 255),
            'locale' => app()->getLocale(),
            'utm' => $this->utm !== [] ? $this->utm : null,
            'referrer' => $this->referrer,
            'status' => 'new',
        ]);

        $this->sent();
    }

    /** The visitor sees the same "sent" state whether the message was stored or caught by a spam trap. */
    private function sent(): void
    {
        $this->submitted = true;

        if ($this->botMode) {
            $this->formMode = null;
            $this->message = '';
            $this->addBot(['kind' => 'answer', 'text' => __('storefront.chat.success'), 'buttons' => []]);
        }
    }

    public function render()
    {
        return view('livewire.storefront.chat-widget');
    }
}
