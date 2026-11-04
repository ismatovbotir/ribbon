<?php

namespace App\Livewire\Admin\Pages;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Create/edit a CMS page (one component for both, like Admin\Articles\Form).
 *
 * Authorization is re-checked inside every action against the *persisted*
 * row, never client input: `pages.manage` for everything, and
 * Page::isEditableBy() so only Super Admin touches legal-column pages —
 * including moving a page into the legal column. System pages keep their
 * immutable key and fixed URL (no slug is generated or edited); custom
 * pages get a per-locale slug generated once at creation.
 */
class Form extends Component
{
    use AuthorizesAdmin;

    protected function adminAccess(): ?string
    {
        return 'pages.manage';
    }

    #[Locked]
    public ?Page $page = null;

    /** @var array<string, string> */
    public array $title = [];

    /** @var array<string, string> */
    public array $lead = [];

    /** @var array<string, string> */
    public array $body = [];

    /** @var array<string, string> */
    public array $metaDescription = [];

    public bool $isPublished = true;

    public bool $showInHeader = false;

    public bool $showInFooter = true;

    public string $footerColumn = 'company';

    public int $sortOrder = 0;

    public function mount(?Page $page = null): void
    {
        $this->authorizeAdmin('pages.manage');

        $locales = config('ribbon.locales');

        if ($page?->exists) {
            abort_unless($page->isEditableBy(Auth::user()), 403, 'Legal pages can only be edited by a Super Admin.');

            $this->page = $page;
            $this->title = $this->fillLocales($page->title ?? []);
            $this->lead = $this->fillLocales($page->lead ?? []);
            $this->body = $this->fillLocales($page->body ?? []);
            $this->metaDescription = $this->fillLocales($page->meta_description ?? []);
            $this->isPublished = $page->is_published;
            $this->showInHeader = $page->show_in_header;
            $this->showInFooter = $page->show_in_footer;
            $this->footerColumn = $page->footer_column;
            $this->sortOrder = $page->sort_order;

            return;
        }

        foreach (['title', 'lead', 'body', 'metaDescription'] as $field) {
            $this->{$field} = array_fill_keys($locales, '');
        }
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function fillLocales(array $values): array
    {
        return collect(config('ribbon.locales'))
            ->mapWithKeys(fn (string $locale) => [$locale => (string) ($values[$locale] ?? '')])
            ->all();
    }

    /**
     * Slug shown (read-only) for a custom page: the stored one, or a live
     * preview while creating. System pages have a fixed path instead.
     */
    public function slugPreview(string $locale): string
    {
        $this->authorizeAdmin('pages.manage');

        if ($this->page) {
            return $this->page->slug[$locale] ?? '';
        }

        return Page::generateUniqueSlug($this->title[$locale] ?? '', $locale);
    }

    public function save(): void
    {
        $this->authorizeAdmin('pages.manage');

        $user = Auth::user();
        $page = $this->page ? Page::query()->findOrFail($this->page->id) : null;

        // Re-check against the DB row (current state), and against the
        // target column: a Moderator may neither edit a legal page nor
        // move a page into the legal column.
        abort_if($page && ! $page->isEditableBy($user), 403, 'Legal pages can only be edited by a Super Admin.');
        abort_if($this->footerColumn === 'legal' && ! $user->isSuperAdmin(), 403, 'Only a Super Admin can place pages in the legal column.');

        $locales = config('ribbon.locales');

        $rules = [
            'isPublished' => ['boolean'],
            'showInHeader' => ['boolean'],
            'showInFooter' => ['boolean'],
            'footerColumn' => ['required', Rule::in(Page::FOOTER_COLUMNS)],
            'sortOrder' => ['integer', 'min:0', 'max:65535'],
        ];

        foreach ($locales as $locale) {
            $rules["title.{$locale}"] = ['required', 'string', 'max:160'];
            $rules["lead.{$locale}"] = ['nullable', 'string', 'max:300'];
            $rules["body.{$locale}"] = ['required', 'string'];
            $rules["metaDescription.{$locale}"] = ['nullable', 'string', 'max:160'];
        }

        $this->validate($rules);

        if ($this->showInHeader) {
            $others = Page::query()->where('show_in_header', true)->when($page, fn ($q) => $q->where('id', '!=', $page->id))->count();

            if ($others >= Page::MAX_HEADER_PAGES) {
                $this->addError('showInHeader', 'At most '.Page::MAX_HEADER_PAGES.' pages can be shown in the header menu.');

                return;
            }
        }

        $data = [
            'title' => $this->title,
            'lead' => $this->lead,
            'body' => $this->body, // sanitized by Page's saving hook
            'meta_description' => $this->metaDescription,
            'is_published' => $this->isPublished,
            'show_in_header' => $this->showInHeader,
            'show_in_footer' => $this->showInFooter,
            'footer_column' => $this->footerColumn,
            'sort_order' => $this->sortOrder,
            'updated_by' => $user->id,
        ];

        $beforeHash = $page ? hash('sha256', json_encode($page->body)) : null;

        if ($page) {
            $page->update($data);
        } else {
            $slug = [];

            foreach ($locales as $locale) {
                $slug[$locale] = Page::generateUniqueSlug($this->title[$locale], $locale);
            }

            $page = Page::create($data + ['slug' => $slug]);
        }

        // Audit trail: who/when/what (no PII). Legal pages also record
        // before/after body hashes so a silent text change is detectable.
        Log::info('page.saved', array_filter([
            'page_id' => $page->id,
            'key' => $page->key,
            'by' => $user->id,
            'legal' => $page->isLegal(),
            'body_hash_before' => $page->isLegal() ? $beforeHash : null,
            'body_hash_after' => $page->isLegal() ? hash('sha256', json_encode($page->body)) : null,
        ], fn ($v) => $v !== null));

        session()->flash('status', 'Page saved.');

        $this->redirectRoute('admin.pages.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.pages.form')->layout('layouts.admin', [
            'title' => $this->page ? 'Edit Page' : 'New Page',
            'breadcrumb' => [
                ['label' => 'Pages', 'url' => route('admin.pages.index')],
                ['label' => $this->page ? ($this->page->title[config('ribbon.locales')[0]] ?? 'Edit') : 'New'],
            ],
        ]);
    }
}
