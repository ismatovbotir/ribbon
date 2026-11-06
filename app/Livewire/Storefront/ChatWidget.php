<?php

namespace App\Livewire\Storefront;

use App\Models\ContactMessage;
use App\Rules\PhoneNumber;
use App\Support\Attribution;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The logo button in the storefront corner. It stores a question with the
 * visitor's phone; `chat-messages:send` delivers it to the Super Admin on
 * Telegram on a schedule, so a Telegram outage never loses a message.
 */
class ChatWidget extends Component
{
    private const MIN_FILL_SECONDS = 3;

    public string $phone = '';

    public string $message = '';

    public bool $submitted = false;

    /** Honeypot: real visitors never fill it. */
    public string $website = '';

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

    public function mount(): void
    {
        $this->renderedAt = now()->getTimestamp();
        $this->page = '/'.ltrim(request()->path(), '/');

        ['utm' => $this->utm, 'referrer' => $this->referrer] = Attribution::current(request());
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

    public function submit(): void
    {
        // Spam traps first: pretend success so bots get no signal.
        if ($this->website !== '' || now()->getTimestamp() - $this->renderedAt < self::MIN_FILL_SECONDS) {
            $this->submitted = true;

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

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.storefront.chat-widget');
    }
}
