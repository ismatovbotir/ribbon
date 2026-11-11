<?php

namespace App\Livewire\Admin\Bot;

use App\Livewire\Concerns\AuthorizesAdmin;
use Livewire\Component;

/** Bot training > Instructions: how to set up, teach, test and switch on the rule-based bot. Static content from lang admin.bot.guide. */
class Guide extends Component
{
    use AuthorizesAdmin;

    protected function adminAccess(): ?string
    {
        return 'bot.manage';
    }

    public function mount(): void
    {
        $this->authorizeAdmin('bot.manage');
    }

    public function render()
    {
        $sections = __('admin.bot.guide');

        return view('livewire.admin.bot.guide', [
            'sections' => is_array($sections) ? $sections : [],
        ])->layout('layouts.admin', [
            'title' => __('admin.bot.tab_guide'),
            'breadcrumb' => [
                ['label' => __('admin.bot.title'), 'url' => route('admin.bot.index')],
                ['label' => __('admin.bot.tab_guide')],
            ],
        ]);
    }
}
