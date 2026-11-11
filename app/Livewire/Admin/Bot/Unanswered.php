<?php

namespace App\Livewire\Admin\Bot;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\BotUnanswered;
use Livewire\Component;
use Livewire\WithPagination;

/** Questions the bot could not answer (masked), most asked first, each with a "Teach" link. */
class Unanswered extends Component
{
    use AuthorizesAdmin;
    use WithPagination;

    protected function adminAccess(): ?string
    {
        return 'bot.manage';
    }

    public function mount(): void
    {
        $this->authorizeAdmin('bot.manage');
    }

    public function dismiss(int $id): void
    {
        $this->authorizeAdmin('bot.manage');

        BotUnanswered::open()->findOrFail($id)->update(['resolved_at' => now()]);

        session()->flash('status', __('admin.bot.dismissed'));
    }

    public function render()
    {
        return view('livewire.admin.bot.unanswered', [
            'questions' => BotUnanswered::open()->orderByDesc('times')->orderByDesc('last_asked_at')->paginate(30),
        ])->layout('layouts.admin', [
            'title' => __('admin.bot.tab_unanswered'),
            'breadcrumb' => [
                ['label' => __('admin.bot.title'), 'url' => route('admin.bot.index')],
                ['label' => __('admin.bot.tab_unanswered')],
            ],
        ]);
    }
}
