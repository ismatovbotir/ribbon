<?php

namespace App\Livewire\Admin\Categories;

use App\Models\Category;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $sortField = 'sort_order';

    #[Url(history: true)]
    public string $sortDirection = 'asc';

    public bool $showCreateForm = false;

    /** @var array<string, string> */
    public array $name = [];

    public bool $isActive = true;

    /**
     * Staged category image for the create form — jpg/png only, max 1MB
     * (see createCategory()). Stored under `categories/` on the `public`
     * disk once saved.
     */
    public $imageUpload = null;

    /**
     * Columns a staff member is allowed to sort this table by. Name/slug
     * are JSON-per-locale — sorting on them is intentionally left out of
     * this first pass (would require picking a locale to sort by, which
     * isn't spec'd yet) to keep the column set honest about what works.
     */
    protected array $sortable = ['sort_order', 'is_active', 'created_at'];

    public function mount(): void
    {
        $this->resetForm();
    }

    /**
     * Read-only slug preview shown beneath the Name field as the admin
     * types — slugs are fully system-generated (no manual override
     * anywhere), so this is purely informational, computed with the same
     * disambiguation logic used at save time so what's previewed is
     * exactly what gets persisted.
     */
    public function slugPreview(string $locale): string
    {
        return Category::generateUniqueSlug($this->name[$locale] ?? '', $locale);
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, $this->sortable, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleCreateForm(): void
    {
        $this->showCreateForm = ! $this->showCreateForm;

        if (! $this->showCreateForm) {
            $this->resetForm();
        }
    }

    public function createCategory(): void
    {
        $locales = config('ribbon.locales');

        $rules = [
            'imageUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ];

        foreach ($locales as $locale) {
            $rules["name.{$locale}"] = ['required', 'string', 'max:120'];
        }

        $this->validate($rules);

        // Slugs are fully system-generated — never sourced from user
        // input — and derived once, here, at creation time. Uniqueness is
        // enforced by disambiguation (Category::generateUniqueSlug), not a
        // validation rule, since there's no manual field left for an admin
        // to correct on collision.
        $slug = [];

        foreach ($locales as $locale) {
            $slug[$locale] = Category::generateUniqueSlug($this->name[$locale], $locale);
        }

        $imagePath = $this->imageUpload ? $this->imageUpload->store('categories', 'public') : null;

        Category::create([
            'name' => $this->name,
            'slug' => $slug,
            'is_active' => $this->isActive,
            'sort_order' => (int) (Category::max('sort_order') ?? 0) + 1,
            'image_path' => $imagePath,
        ]);

        $this->resetForm();
        $this->showCreateForm = false;
        $this->resetPage();

        session()->flash('status', 'Category created.');
    }

    /**
     * Clears a staged (not-yet-uploaded) image choice from the create form.
     */
    public function removeImageUpload(): void
    {
        $this->imageUpload = null;
    }

    protected function resetForm(): void
    {
        $locales = config('ribbon.locales');

        $this->name = array_fill_keys($locales, '');
        $this->isActive = true;
        $this->imageUpload = null;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    /**
     * Locales among the create form's governed fields (name) that are
     * still empty — drives the locale tab strip's completion dot.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function incompleteLocales(): array
    {
        return collect(config('ribbon.locales'))
            ->filter(fn (string $locale) => blank($this->name[$locale] ?? null))
            ->values()
            ->all();
    }

    public function render()
    {
        $sortField = in_array($this->sortField, $this->sortable, true) ? $this->sortField : 'sort_order';
        $defaultLocale = config('ribbon.locales')[0];

        $categories = Category::query()
            ->withCount('parameters')
            ->when($this->search !== '', function ($query) use ($defaultLocale) {
                $query->where(function ($query) use ($defaultLocale) {
                    $query->where("name->{$defaultLocale}", 'like', "%{$this->search}%")
                        ->orWhere("slug->{$defaultLocale}", 'like', "%{$this->search}%");
                });
            })
            ->orderBy($sortField, $this->sortDirection)
            ->paginate(25);

        return view('livewire.admin.categories.index', [
            'categories' => $categories,
            'defaultLocale' => $defaultLocale,
        ])->layout('layouts.admin', [
            'title' => 'Categories',
            'breadcrumb' => [
                ['label' => 'Categories'],
            ],
        ]);
    }
}
