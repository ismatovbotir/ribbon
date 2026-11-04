<?php

namespace App\Livewire\Admin\Pages;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CMS pages list. Needs `pages.manage` (Super Admin and Moderator).
 * Legal-column pages are listed for everyone but only Super Admin can edit
 * or delete them (Page::isEditableBy()); system pages are never deletable.
 */
class Index extends Component
{
    use AuthorizesAdmin;
    use WithPagination;

    protected function adminAccess(): ?string
    {
        return 'pages.manage';
    }

    /** '' | published | draft */
    #[Url(history: true)]
    public string $status = '';

    /** '' | header | company | legal */
    #[Url(history: true)]
    public string $placement = '';

    public function updatedStatus(): void
    {
        $this->authorizeAdmin('pages.manage');
        $this->resetPage();
    }

    public function updatedPlacement(): void
    {
        $this->authorizeAdmin('pages.manage');
        $this->resetPage();
    }

    /**
     * Deletes a custom page. The id is client-controlled, so the row is
     * re-fetched and re-authorized here, never trusted from the list.
     */
    public function delete(int $pageId): void
    {
        $this->authorizeAdmin('pages.manage');

        $page = Page::query()->findOrFail($pageId);

        abort_if($page->isSystem(), 403, 'System pages cannot be deleted.');
        abort_unless($page->isEditableBy(Auth::user()), 403);

        $page->delete();

        session()->flash('status', 'Page deleted.');
    }

    public function render()
    {
        Gate::authorize('admin-permission', 'pages.manage');

        $pages = Page::query()
            ->when($this->status === 'published', fn ($q) => $q->where('is_published', true))
            ->when($this->status === 'draft', fn ($q) => $q->where('is_published', false))
            ->when($this->placement === 'header', fn ($q) => $q->where('show_in_header', true))
            ->when(in_array($this->placement, Page::FOOTER_COLUMNS, true), fn ($q) => $q->where('show_in_footer', true)->where('footer_column', $this->placement))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(25);

        return view('livewire.admin.pages.index', [
            'pages' => $pages,
            'defaultLocale' => config('ribbon.locales')[0],
            'user' => Auth::user(),
        ])->layout('layouts.admin', [
            'title' => 'Pages',
            'breadcrumb' => [['label' => 'Pages']],
        ]);
    }
}
