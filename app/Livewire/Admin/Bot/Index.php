<?php

namespace App\Livewire\Admin\Bot;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\BotAnswer;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Bot training: the answers staff taught, plus the website / Telegram on-off
 * switches. The switches are a site setting, so only Super Admin may flip them
 * (Moderators see their state).
 */
class Index extends Component
{
    use AuthorizesAdmin;

    protected function adminAccess(): ?string
    {
        return 'bot.manage';
    }

    public bool $webEnabled = false;

    public bool $telegramEnabled = false;

    #[Locked]
    public ?int $deletingId = null;

    public function mount(): void
    {
        $this->authorizeAdmin('bot.manage');

        $settings = Setting::current();
        $this->webEnabled = (bool) $settings->bot_web_enabled;
        $this->telegramEnabled = (bool) $settings->bot_telegram_enabled;
    }

    public function updatedWebEnabled(bool $value): void
    {
        $this->authorizeSuperAdmin();
        Setting::current()->update(['bot_web_enabled' => $value]);
    }

    public function updatedTelegramEnabled(bool $value): void
    {
        $this->authorizeSuperAdmin();
        Setting::current()->update(['bot_telegram_enabled' => $value]);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAdmin('bot.manage');
        $this->deletingId = BotAnswer::findOrFail($id)->id;
    }

    public function cancelDelete(): void
    {
        $this->authorizeAdmin('bot.manage');
        $this->deletingId = null;
    }

    #[Computed]
    public function deletingAnswer(): ?BotAnswer
    {
        return $this->deletingId ? BotAnswer::find($this->deletingId) : null;
    }

    public function deleteAnswer(): void
    {
        $this->authorizeAdmin('bot.manage');

        BotAnswer::find($this->deletingId)?->delete();
        $this->deletingId = null;
        unset($this->deletingAnswer);

        session()->flash('status', __('admin.bot.deleted'));
    }

    public function render()
    {
        $answers = BotAnswer::query()->orderByDesc('is_start')->orderBy('name')->get();

        return view('livewire.admin.bot.index', [
            'answers' => $answers,
            'canSwitch' => Gate::allows('super-admin'),
            'hasStart' => $answers->contains(fn ($a) => $a->is_start && $a->is_active),
        ])->layout('layouts.admin', [
            'title' => __('admin.bot.title'),
            'breadcrumb' => [['label' => __('admin.bot.title')]],
        ]);
    }
}
