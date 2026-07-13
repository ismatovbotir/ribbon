<?php

namespace App\Livewire\Admin\Banners;

use App\Models\Banner;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $sortField = 'sort_order';

    #[Url(history: true)]
    public string $sortDirection = 'asc';

    /**
     * Columns a staff member is allowed to sort this table by. `title` is
     * JSON-per-locale — sorting on it is left out for the same reason as
     * Categories' name column (would require picking a locale to sort by).
     *
     * @var array<int, string>
     */
    protected array $sortable = ['sort_order', 'is_active', 'starts_at', 'ends_at', 'created_at'];

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

    /**
     * Derives the 4-state display status ("Live now" / "Scheduled" /
     * "Expired" / "Inactive") from `Banner::isCurrentlyLive()` plus the
     * date-window fields — `is_active` alone is not enough to explain to
     * staff *why* a banner isn't showing right now. `isCurrentlyLive()`
     * remains the single source of truth for the "is it live right now"
     * boolean; this only adds the extra branching needed to label the
     * non-live states, it doesn't re-derive that boolean itself.
     *
     * @return array{label: string, variant: string}
     */
    public static function statusMeta(Banner $banner): array
    {
        if ($banner->isCurrentlyLive()) {
            return ['label' => 'Live now', 'variant' => 'success'];
        }

        if (! $banner->is_active) {
            return ['label' => 'Inactive', 'variant' => 'muted'];
        }

        if ($banner->starts_at !== null && $banner->starts_at->isFuture()) {
            return ['label' => 'Scheduled', 'variant' => 'info'];
        }

        if ($banner->ends_at !== null && $banner->ends_at->isPast()) {
            return ['label' => 'Expired', 'variant' => 'muted'];
        }

        // Not reachable given isCurrentlyLive()'s own logic (active + no
        // future start + no past end implies live), but fall back to a
        // safe, non-misleading label rather than nothing.
        return ['label' => 'Inactive', 'variant' => 'muted'];
    }

    public function render()
    {
        $sortField = in_array($this->sortField, $this->sortable, true) ? $this->sortField : 'sort_order';
        $defaultLocale = config('ribbon.locales')[0];

        $banners = Banner::query()
            ->when($this->search !== '', function ($query) use ($defaultLocale) {
                $query->where("title->{$defaultLocale}", 'like', "%{$this->search}%");
            })
            ->orderBy($sortField, $this->sortDirection)
            ->paginate(25);

        return view('livewire.admin.banners.index', [
            'banners' => $banners,
            'defaultLocale' => $defaultLocale,
            'placementLabels' => Form::PLACEMENTS,
        ])->layout('layouts.admin', [
            'title' => 'Banners',
            'breadcrumb' => [
                ['label' => 'Banners'],
            ],
        ]);
    }
}
