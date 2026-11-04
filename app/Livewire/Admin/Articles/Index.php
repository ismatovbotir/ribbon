<?php

namespace App\Livewire\Admin\Articles;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\Article;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesAdmin;

    protected function adminAccess(): ?string
    {
        return 'content.manage';
    }

    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $type = '';

    #[Url(history: true)]
    public string $sortField = 'created_at';

    #[Url(history: true)]
    public string $sortDirection = 'desc';

    /**
     * Columns a staff member is allowed to sort this table by. `title` is
     * JSON-per-locale — sorting on it is left out for the same reason as
     * Categories'/Banners' name/title columns (would require picking a
     * locale to sort by).
     *
     * @var array<int, string>
     */
    protected array $sortable = ['published_at', 'created_at'];

    public function sortBy(string $field): void
    {
        $this->authorizeAdmin('content.manage');

        if (! in_array($field, $this->sortable, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    /** The article awaiting delete confirmation. */
    #[Locked]
    public ?int $deletingId = null;

    public function confirmDelete(int $id): void
    {
        $this->authorizeAdmin('content.manage');

        $this->deletingId = Article::findOrFail($id)->id;
    }

    public function cancelDelete(): void
    {
        $this->authorizeAdmin('content.manage');

        $this->deletingId = null;
    }

    #[Computed]
    public function deletingArticle(): ?Article
    {
        return $this->deletingId ? Article::find($this->deletingId) : null;
    }

    public function deleteArticle(): void
    {
        $this->authorizeAdmin('content.manage');

        Article::find($this->deletingId)?->delete();

        $this->deletingId = null;
        unset($this->deletingArticle);
        $this->resetPage();

        session()->flash('status', 'Article deleted.');
    }

    public function updatedSearch(): void
    {
        $this->authorizeAdmin('content.manage');

        $this->resetPage();
    }

    /**
     * Toggling the same type again clears the filter — same single-select-
     * with-an-off-state pattern as Admin\Sellers\Index's status chips.
     */
    public function filterByType(string $type): void
    {
        $this->authorizeAdmin('content.manage');

        $this->type = $this->type === $type ? '' : $type;
        $this->resetPage();
    }

    /**
     * Derives the 3-state display status ("Draft" / "Scheduled" /
     * "Published") from `Article::isPublished()` plus `published_at` —
     * `isPublished()` remains the single source of truth for "is it live
     * right now"; this only adds the branching needed to label the two
     * non-live states distinctly (never published at all vs. published but
     * still in the future) rather than lumping both under "Draft".
     *
     * @return array{label: string, variant: string}
     */
    public static function statusMeta(Article $article): array
    {
        if ($article->isPublished()) {
            return ['label' => 'Published', 'variant' => 'success'];
        }

        if ($article->published_at !== null && $article->published_at->isFuture()) {
            return ['label' => 'Scheduled', 'variant' => 'info'];
        }

        return ['label' => 'Draft', 'variant' => 'muted'];
    }

    public function render()
    {
        $sortField = in_array($this->sortField, $this->sortable, true) ? $this->sortField : 'created_at';
        $defaultLocale = config('ribbon.locales')[0];

        $articles = Article::query()
            ->when($this->search !== '', function ($query) use ($defaultLocale) {
                $query->where("title->{$defaultLocale}", 'like', "%{$this->search}%");
            })
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->orderBy($sortField, $this->sortDirection)
            ->paginate(25);

        return view('livewire.admin.articles.index', [
            'articles' => $articles,
            'defaultLocale' => $defaultLocale,
        ])->layout('layouts.admin', [
            'title' => 'Articles',
            'breadcrumb' => [
                ['label' => 'Articles'],
            ],
        ]);
    }
}
