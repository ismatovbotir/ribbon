<?php

namespace App\Livewire\Admin\Geography\Regions;

use App\Models\Country;
use App\Models\Region;
use App\Models\Seller;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Middle level of the Country -> Region -> City geography tree, scoped to
 * one Country (route-model-bound). Mirrors Countries\Index's list +
 * inline-create/edit-form pattern; drilling into a region's Cities happens
 * on Admin\Geography\Cities\Index.
 */
class Index extends Component
{
    use WithPagination;

    public Country $country;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $sortField = 'sort_order';

    #[Url(history: true)]
    public string $sortDirection = 'asc';

    public bool $showCreateForm = false;

    /** @var array<string, string> */
    public array $name = [];

    // ---- Inline row edit ----

    public ?int $editingRegionId = null;

    /** @var array<string, string> */
    public array $editingName = [];

    // ---- Delete confirmation modal ----

    public bool $showDeleteConfirm = false;

    public ?int $deletingRegionId = null;

    protected array $sortable = ['sort_order', 'created_at'];

    public function mount(Country $country): void
    {
        $this->country = $country;
        $this->resetForm();
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

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------

    public function toggleCreateForm(): void
    {
        $this->showCreateForm = ! $this->showCreateForm;

        if (! $this->showCreateForm) {
            $this->resetForm();
        }
    }

    public function createRegion(): void
    {
        $rules = [];

        foreach (config('ribbon.locales') as $locale) {
            $rules["name.{$locale}"] = ['required', 'string', 'max:120'];
        }

        $this->validate($rules);

        Region::create([
            'country_id' => $this->country->id,
            'name' => $this->name,
            'sort_order' => (int) ($this->country->regions()->max('sort_order') ?? 0) + 1,
        ]);

        $this->resetForm();
        $this->showCreateForm = false;
        $this->resetPage();

        session()->flash('status', 'Region created.');
    }

    protected function resetForm(): void
    {
        $this->name = array_fill_keys(config('ribbon.locales'), '');
        $this->resetErrorBag();
        $this->resetValidation();
    }

    #[Computed]
    public function incompleteLocales(): array
    {
        return collect(config('ribbon.locales'))
            ->filter(fn (string $locale) => blank($this->name[$locale] ?? null))
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Inline edit
    // ------------------------------------------------------------------

    public function startEdit(int $regionId): void
    {
        $region = $this->country->regions()->findOrFail($regionId);

        $this->editingRegionId = $region->id;
        $this->editingName = collect(config('ribbon.locales'))
            ->mapWithKeys(fn (string $locale) => [$locale => $region->name[$locale] ?? ''])
            ->all();
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->editingRegionId = null;
        $this->editingName = [];
        $this->resetErrorBag();
    }

    public function updateRegion(): void
    {
        if (! $this->editingRegionId) {
            return;
        }

        $rules = [];

        foreach (config('ribbon.locales') as $locale) {
            $rules["editingName.{$locale}"] = ['required', 'string', 'max:120'];
        }

        $this->validate($rules);

        $this->country->regions()->whereKey($this->editingRegionId)->update(['name' => $this->editingName]);

        $this->cancelEdit();

        session()->flash('status', 'Region updated.');
    }

    #[Computed]
    public function incompleteEditingLocales(): array
    {
        return collect(config('ribbon.locales'))
            ->filter(fn (string $locale) => blank($this->editingName[$locale] ?? null))
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------------

    public function confirmDeleteRegion(int $regionId): void
    {
        $this->deletingRegionId = $regionId;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
        $this->deletingRegionId = null;
    }

    #[Computed]
    public function deletingRegion(): ?Region
    {
        if (! $this->deletingRegionId) {
            return null;
        }

        return Region::withCount('cities')->find($this->deletingRegionId);
    }

    #[Computed]
    public function deleteBlockedByCitiesCount(): int
    {
        return $this->deletingRegion?->cities_count ?? 0;
    }

    #[Computed]
    public function deleteBlockedBySellersCount(): int
    {
        if (! $this->deletingRegionId) {
            return 0;
        }

        return Seller::where('region_id', $this->deletingRegionId)->count();
    }

    public function deleteRegion(): void
    {
        if ($this->deleteBlockedByCitiesCount > 0 || $this->deleteBlockedBySellersCount > 0) {
            return;
        }

        Region::where('id', $this->deletingRegionId)->delete();

        $this->showDeleteConfirm = false;
        $this->deletingRegionId = null;

        session()->flash('status', 'Region deleted.');
    }

    public function render()
    {
        $sortField = in_array($this->sortField, $this->sortable, true) ? $this->sortField : 'sort_order';
        $defaultLocale = config('ribbon.locales')[0];

        $regions = $this->country->regions()
            ->withCount('cities')
            ->when($this->search !== '', function ($query) use ($defaultLocale) {
                $query->where("name->{$defaultLocale}", 'like', "%{$this->search}%");
            })
            ->orderBy($sortField, $this->sortDirection)
            ->paginate(25);

        return view('livewire.admin.geography.regions.index', [
            'regions' => $regions,
            'defaultLocale' => $defaultLocale,
        ])->layout('layouts.admin', [
            'title' => $this->country->name[$defaultLocale] ?? 'Regions',
            'breadcrumb' => [
                ['label' => 'Geography', 'url' => route('admin.geography.countries.index')],
                ['label' => $this->country->name[$defaultLocale] ?? 'Country'],
            ],
        ]);
    }
}
