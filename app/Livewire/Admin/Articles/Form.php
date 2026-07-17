<?php

namespace App\Livewire\Admin\Articles;

use App\Models\Article;
use App\Models\Category;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Single component for both Create and Edit — same wire:model bindings
 * regardless of whether $article is null, mirroring Admin\Banners\Form's
 * documented reasoning for why that matters (no @if/@else swap between
 * differently-bound form blocks at the same DOM position).
 */
class Form extends Component
{
    use WithFileUploads;

    public ?Article $article = null;

    public string $type = 'article';

    /** @var array<string, string> */
    public array $title = [];

    /** @var array<string, string> */
    public array $excerpt = [];

    /** @var array<string, string> */
    public array $body = [];

    public $coverImageUpload = null;

    public ?string $existingCoverImagePath = null;

    // Optional tags to catalog categories (many-to-many), surfaced as
    // "related articles" on each tagged category's storefront page — see
    // Article::categories()'s docblock on why this isn't required. Holds
    // category IDs as strings (native HTML checkbox `value` semantics),
    // coerced to int only where compared/persisted.
    /** @var array<int, string> */
    public array $categoryIds = [];

    // Shared upload buffer for in-body Trix images (x-rich-text-editor
    // component) — $wire.upload() populates this, then storeEditorImage()
    // below moves it to public storage and returns the URL Trix embeds.
    public $editorImageUpload = null;

    // Bound to <input type="datetime-local">, kept as the plain
    // "Y-m-d\TH:i" string the input produces/expects — same convention as
    // Admin\Banners\Form's startsAt/endsAt. Null/blank = draft, never shown
    // on the storefront (see Article::isPublished()).
    public ?string $publishedAt = null;

    public function mount(?Article $article = null): void
    {
        $this->article = $article;

        if ($this->article) {
            $this->type = $this->article->type;
            $this->title = $this->fillLocales($this->article->title);
            $this->excerpt = $this->fillLocales($this->article->excerpt ?? []);
            $this->body = $this->fillLocales($this->article->body);
            $this->existingCoverImagePath = $this->article->cover_image_path;
            $this->categoryIds = $this->article->categories()->pluck('categories.id')->map(fn ($id) => (string) $id)->all();
            $this->publishedAt = $this->article->published_at?->format('Y-m-d\TH:i');

            return;
        }

        $this->title = array_fill_keys(config('ribbon.locales'), '');
        $this->excerpt = array_fill_keys(config('ribbon.locales'), '');
        $this->body = array_fill_keys(config('ribbon.locales'), '');
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    protected function fillLocales(array $values): array
    {
        return collect(config('ribbon.locales'))
            ->mapWithKeys(fn (string $locale) => [$locale => $values[$locale] ?? ''])
            ->all();
    }

    #[Computed]
    public function incompleteLocales(): array
    {
        return collect(config('ribbon.locales'))
            ->filter(fn (string $locale) => blank($this->title[$locale] ?? null) || blank($this->body[$locale] ?? null))
            ->values()
            ->all();
    }

    /**
     * Live slug preview shown next to each locale's title field while
     * typing — slugs are fully system-generated, never an admin-editable
     * input, mirroring Category's identical live-preview pattern. On an
     * existing article this shows the slug it already has (unaffected by
     * further title edits — see generateUniqueSlug()'s docblock on why
     * slugs aren't regenerated on rename) rather than a stale re-preview.
     */
    public function slugPreview(string $locale): string
    {
        if ($this->article) {
            return $this->article->slug[$locale] ?? '';
        }

        return Article::generateUniqueSlug($this->title[$locale] ?? '', $locale);
    }

    /**
     * Active categories for the optional "related articles" targeting
     * checkboxes — same source query as Admin\Banners\Form::categories(),
     * just multi-selected here.
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::where('is_active', true)->orderBy('sort_order')->get();
    }

    public function removeCoverImage(): void
    {
        $this->coverImageUpload = null;
        $this->existingCoverImagePath = null;
    }

    public function storeEditorImage(): string
    {
        $this->validate([
            'editorImageUpload' => ['required', 'image', 'max:4096'],
        ]);

        $path = $this->editorImageUpload->store('articles/body', 'public');

        $this->editorImageUpload = null;

        return Storage::disk('public')->url($path);
    }

    public function save(): void
    {
        $locales = config('ribbon.locales');

        $rules = [
            'type' => ['required', Rule::in(Article::TYPES)],
            'coverImageUpload' => ['nullable', 'image', 'max:4096'],
            'categoryIds' => ['array'],
            'categoryIds.*' => [Rule::exists('categories', 'id')],
            'publishedAt' => ['nullable', 'date'],
        ];

        foreach ($locales as $locale) {
            $rules["title.{$locale}"] = ['required', 'string', 'max:160'];
            $rules["excerpt.{$locale}"] = ['nullable', 'string', 'max:300'];
            $rules["body.{$locale}"] = ['required', 'string'];
        }

        $this->validate($rules);

        $coverImagePath = $this->coverImageUpload
            ? $this->coverImageUpload->store('articles', 'public')
            : $this->existingCoverImagePath;

        $sanitizedBody = collect($this->body)
            ->map(fn (string $html) => HtmlSanitizer::clean($html))
            ->all();

        $data = [
            'type' => $this->type,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $sanitizedBody,
            'cover_image_path' => $coverImagePath,
            'published_at' => $this->publishedAt ? Carbon::parse($this->publishedAt) : null,
        ];

        if ($this->article) {
            $this->article->update($data);
            $article = $this->article;
        } else {
            $slug = [];

            foreach ($locales as $locale) {
                $slug[$locale] = Article::generateUniqueSlug($this->title[$locale], $locale);
            }

            $data['slug'] = $slug;
            $data['created_by'] = Auth::id();

            $article = Article::create($data);
        }

        $article->categories()->sync($this->categoryIds);

        session()->flash('status', 'Article saved.');

        $this->redirectRoute('admin.articles.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.articles.form')->layout('layouts.admin', [
            'title' => $this->article ? 'Edit Article' : 'New Article',
            'breadcrumb' => [
                ['label' => 'Articles', 'url' => route('admin.articles.index')],
                ['label' => $this->article ? ($this->article->title[config('ribbon.locales')[0]] ?? 'Edit') : 'New'],
            ],
        ]);
    }
}
