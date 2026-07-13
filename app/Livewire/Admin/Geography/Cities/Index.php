<?php

namespace App\Livewire\Admin\Geography\Cities;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Seller;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Terminal level of the Country -> Region -> City geography tree, scoped to
 * one Region (route-model-bound, nested under its Country in the URL for a
 * readable breadcrumb path). Mirrors Regions\Index's list +
 * inline-create/edit-form pattern; there is nothing to drill into further,
 * so a City's Delete action is the only place its Seller-reference guard is
 * exercised directly (Country/Region deletion is blocked earlier by their
 * children first).
 */
class Index extends Component
{
    use WithPagination;

    public Country $country;

    public Region $region;

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

    public ?int $editingCityId = null;

    /** @var array<string, string> */
    public array $editingName = [];

    // ---- Delete confirmation modal ----

    public bool $showDeleteConfirm = false;

    public ?int $deletingCityId = null;

    protected array $sortable = ['sort_order', 'created_at'];

    public function mount(Country $country, Region $region): void
    {
        // The URL nests region under country for a readable breadcrumb, but
        // route-model binding resolves each independently — guard against a
        // mismatched pair (e.g. a stale/tampered link) rather than silently
        // showing a region under the wrong country.
        abort_unless($region->country_id === $country->id, 404);

        $this->country = $country;
        $this->region = $region;
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

    public function createCity(): void
    {
        $rules = [];

        foreach (config('ribbon.locales') as $locale) {
            $rules["name.{$locale}"] = ['required', 'string', 'max:120'];
        }

        $this->validate($rules);

        City::create([
            'region_id' => $this->region->id,
            'name' => $this->name,
            'sort_order' => (int) ($this->region->cities()->max('sort_order') ?? 0) + 1,
        ]);

        $this->resetForm();
        $this->showCreateForm = false;
        $this->resetPage();

        session()->flash('status', 'City created.');
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

    public function startEdit(int $cityId): void
    {
        $city = $this->region->cities()->findOrFail($cityId);

        $this->editingCityId = $city->id;
        $this->editingName = collect(config('ribbon.locales'))
            ->mapWithKeys(fn (string $locale) => [$locale => $city->name[$locale] ?? ''])
            ->all();
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->editingCityId = null;
        $this->editingName = [];
        $this->resetErrorBag();
    }

    public function updateCity(): void
    {
        if (! $this->editingCityId) {
            return;
        }

        $rules = [];

        foreach (config('ribbon.locales') as $locale) {
            $rules["editingName.{$locale}"] = ['required', 'string', 'max:120'];
        }

        $this->validate($rules);

        $this->region->cities()->whereKey($this->editingCityId)->update(['name' => $this->editingName]);

        $this->cancelEdit();

        session()->flash('status', 'City updated.');
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

    public function confirmDeleteCity(int $cityId): void
    {
        $this->deletingCityId = $cityId;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
        $this->deletingCityId = null;
    }

    #[Computed]
    public function deletingCity(): ?City
    {
        if (! $this->deletingCityId) {
            return null;
        }

        return City::find($this->deletingCityId);
    }

    #[Computed]
    public function deleteBlockedBySellersCount(): int
    {
        if (! $this->deletingCityId) {
            return 0;
        }

        return Seller::where('city_id', $this->deletingCityId)->count();
    }

    public function deleteCity(): void
    {
        if ($this->deleteBlockedBySellersCount > 0) {
            return;
        }

        City::where('id', $this->deletingCityId)->delete();

        $this->showDeleteConfirm = false;
        $this->deletingCityId = null;

        session()->flash('status', 'City deleted.');
    }

    public function render()
    {
        $sortField = in_array($this->sortField, $this->sortable, true) ? $this->sortField : 'sort_order';
        $defaultLocale = config('ribbon.locales')[0];

        $cities = $this->region->cities()
            ->when($this->search !== '', function ($query) use ($defaultLocale) {
                $query->where("name->{$defaultLocale}", 'like', "%{$this->search}%");
            })
            ->orderBy($sortField, $this->sortDirection)
            ->paginate(25);

        return view('livewire.admin.geography.cities.index', [
            'cities' => $cities,
            'defaultLocale' => $defaultLocale,
        ])->layout('layouts.admin', [
            'title' => $this->region->name[$defaultLocale] ?? 'Cities',
            'breadcrumb' => [
                ['label' => 'Geography', 'url' => route('admin.geography.countries.index')],
                ['label' => $this->country->name[$defaultLocale] ?? 'Country', 'url' => route('admin.geography.regions.index', $this->country)],
                ['label' => $this->region->name[$defaultLocale] ?? 'Region'],
            ],
        ]);
    }
}
