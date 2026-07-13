<?php

namespace App\Livewire\Admin\Banners;

use App\Models\Banner;
use App\Models\Category;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Single component for both Create and Edit — every field below uses the
 * exact same wire:model binding regardless of whether $banner is null, so
 * there is no @if/@else swap between differently-bound form blocks at the
 * same DOM position (the failure mode that previously corrupted data in the
 * seller-registration wizard by leaving a stale Alpine binding on a reused
 * node). Nothing here needs a distinct wire:key for that reason.
 */
class Form extends Component
{
    use WithFileUploads;

    /**
     * Provisional storefront placement slots — no buyer storefront exists
     * yet to confirm real placements against, so this is a small closed set
     * describing plausible "where on the buyer storefront this banner could
     * eventually go" locations (home hero carousel, a secondary strip
     * further down the homepage, the top of a category listing). Revisit
     * once the storefront layout is actually built.
     *
     * @var array<string, string>
     */
    public const PLACEMENTS = [
        'home_hero' => 'Home — Hero',
        'home_secondary' => 'Home — Secondary strip',
        'category_top' => 'Category — Top banner',
    ];

    public ?Banner $banner = null;

    /** @var array<string, string> */
    public array $title = [];

    public $imageUpload = null;

    public ?string $existingImagePath = null;

    public $mobileImageUpload = null;

    public ?string $existingMobileImagePath = null;

    public ?string $linkUrl = null;

    public string $placement = '';

    // Optional category targeting — only meaningful for `category_top`
    // placements, but always shown as an optional field rather than
    // conditionally hidden (a generic/sitewide banner is also valid for
    // category_top when null). Bound to the category's `id` (int), or null
    // for "no specific category".
    public ?int $categoryId = null;

    public int $sortOrder = 0;

    public bool $isActive = true;

    // Bound to <input type="datetime-local">, so kept as the plain
    // "Y-m-d\TH:i" string the input produces/expects rather than a Carbon
    // instance on the component itself.
    public ?string $startsAt = null;

    public ?string $endsAt = null;

    public function mount(?Banner $banner = null): void
    {
        $this->banner = $banner;

        if ($this->banner) {
            $this->title = $this->fillLocales($this->banner->title);
            $this->existingImagePath = $this->banner->image_path;
            $this->existingMobileImagePath = $this->banner->mobile_image_path;
            $this->linkUrl = $this->banner->link_url;
            $this->placement = $this->banner->placement;
            $this->categoryId = $this->banner->category_id;
            $this->sortOrder = $this->banner->sort_order;
            $this->isActive = $this->banner->is_active;
            $this->startsAt = $this->banner->starts_at?->format('Y-m-d\TH:i');
            $this->endsAt = $this->banner->ends_at?->format('Y-m-d\TH:i');

            return;
        }

        $this->title = array_fill_keys(config('ribbon.locales'), '');
        $this->placement = array_key_first(self::PLACEMENTS);
        $this->sortOrder = (int) (Banner::max('sort_order') ?? -1) + 1;
        $this->isActive = true;
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
            ->filter(fn (string $locale) => blank($this->title[$locale] ?? null))
            ->values()
            ->all();
    }

    /**
     * Active categories for the optional targeting select — only
     * meaningfully relevant when `placement === 'category_top'`, but
     * offered regardless of placement (see the categoryId property's
     * docblock).
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::where('is_active', true)->orderBy('sort_order')->get();
    }

    public function removeImage(): void
    {
        $this->imageUpload = null;
        $this->existingImagePath = null;
    }

    public function removeMobileImage(): void
    {
        $this->mobileImageUpload = null;
        $this->existingMobileImagePath = null;
    }

    public function save(): void
    {
        $locales = config('ribbon.locales');

        $rules = [
            'imageUpload' => [$this->existingImagePath ? 'nullable' : 'required', 'image', 'max:4096'],
            'mobileImageUpload' => ['nullable', 'image', 'max:4096'],
            'linkUrl' => ['nullable', 'string', 'max:2048'],
            'placement' => ['required', Rule::in(array_keys(self::PLACEMENTS))],
            'categoryId' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'sortOrder' => ['required', 'integer', 'min:0'],
            'isActive' => ['boolean'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after:startsAt'],
        ];

        foreach ($locales as $locale) {
            $rules["title.{$locale}"] = ['required', 'string', 'max:160'];
        }

        $this->validate($rules);

        // A banner needs *some* image once saved — either a freshly
        // uploaded one, or (on edit, when the admin didn't touch the image
        // field) the one it already had. If both are gone (existing image
        // was removed and nothing new was chosen) the `required`-when-empty
        // rule above already blocked the submit, so this is just wiring the
        // final value, not a second validation pass.
        $imagePath = $this->imageUpload
            ? $this->imageUpload->store('banners', 'public')
            : $this->existingImagePath;

        $mobileImagePath = $this->mobileImageUpload
            ? $this->mobileImageUpload->store('banners', 'public')
            : $this->existingMobileImagePath;

        $data = [
            'title' => $this->title,
            'image_path' => $imagePath,
            'mobile_image_path' => $mobileImagePath,
            'link_url' => $this->linkUrl,
            'placement' => $this->placement,
            'category_id' => $this->categoryId,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'starts_at' => $this->startsAt ? Carbon::parse($this->startsAt) : null,
            'ends_at' => $this->endsAt ? Carbon::parse($this->endsAt) : null,
        ];

        if ($this->banner) {
            $this->banner->update($data);
        } else {
            // created_by is nullable and there's no admin auth/session yet
            // to attribute this to (see layouts/admin.blade.php's "No admin
            // auth yet" note) — left null rather than fabricating a
            // placeholder admin, since nothing downstream requires it to be
            // non-null the way Seller::approve()/reject() do.
            Banner::create($data);
        }

        session()->flash('status', 'Banner saved.');

        $this->redirectRoute('admin.banners.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.banners.form')->layout('layouts.admin', [
            'title' => $this->banner ? 'Edit Banner' : 'New Banner',
            'breadcrumb' => [
                ['label' => 'Banners', 'url' => route('admin.banners.index')],
                ['label' => $this->banner ? ($this->banner->title[config('ribbon.locales')[0]] ?? 'Edit') : 'New'],
            ],
        ]);
    }
}
