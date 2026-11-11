<?php

namespace App\Livewire\Admin\Bot;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Services\Bot\BotEngine;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Admin test chat: talks to the real engine with learning off (no hit counts,
 * nothing saved to Unanswered) and shows why each reply was chosen. The
 * transcript is #[Locked], so button presses are taken from the server's
 * copy, never from client-supplied action strings.
 */
class TestChat extends Component
{
    use AuthorizesAdmin;

    private const MAX_MESSAGES = 60;

    protected function adminAccess(): ?string
    {
        return 'bot.manage';
    }

    public string $locale = '';

    public string $text = '';

    /** @var list<array{from: string, text: string, buttons?: array, matchedBy?: ?string, form?: ?string, kind?: string}> */
    #[Locked]
    public array $messages = [];

    public function mount(BotEngine $engine): void
    {
        $this->authorizeAdmin('bot.manage');

        $this->locale = in_array(app()->getLocale(), config('ribbon.locales'), true) ? app()->getLocale() : config('ribbon.locales')[0];
        $this->restart($engine);
    }

    public function updatedLocale(): void
    {
        $this->authorizeAdmin('bot.manage');

        if (! in_array($this->locale, config('ribbon.locales'), true)) {
            $this->locale = config('ribbon.locales')[0];
        }

        $this->restart(app(BotEngine::class));
    }

    public function restart(BotEngine $engine): void
    {
        $this->authorizeAdmin('bot.manage');

        $this->messages = [];
        $this->addBot($engine->start($this->locale)->toArray());
    }

    public function send(BotEngine $engine): void
    {
        $this->authorizeAdmin('bot.manage');

        $text = trim(mb_substr($this->text, 0, 500));
        $this->text = '';

        if ($text === '') {
            return;
        }

        $this->add(['from' => 'user', 'text' => $text]);
        $this->addBot($engine->ask($text, $this->locale, 'admin', learn: false)->toArray());
    }

    public function press(BotEngine $engine, int $message, int $button): void
    {
        $this->authorizeAdmin('bot.manage');

        $picked = $this->messages[$message]['buttons'][$button] ?? null;

        if (! $picked || $picked['url']) {
            return;
        }

        $this->add(['from' => 'user', 'text' => $picked['label']]);
        $this->addBot($engine->press($picked['action'], $picked['target'], $this->locale, learn: false)->toArray());
    }

    private function addBot(array $reply): void
    {
        $this->add(['from' => 'bot'] + $reply);
    }

    private function add(array $message): void
    {
        $this->messages[] = $message;
        $this->messages = array_slice($this->messages, -self::MAX_MESSAGES);
    }

    public function render()
    {
        return view('livewire.admin.bot.test-chat')->layout('layouts.admin', [
            'title' => __('admin.bot.tab_test'),
            'breadcrumb' => [
                ['label' => __('admin.bot.title'), 'url' => route('admin.bot.index')],
                ['label' => __('admin.bot.tab_test')],
            ],
        ]);
    }
}
