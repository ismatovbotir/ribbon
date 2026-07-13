<?php

namespace App\Livewire\Admin\Geography\Countries;

use App\Models\Country;
use App\Models\Seller;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Top level of the Country -> Region -> City geography tree (genuinely
 * nested, unlike Categories' deliberate flatness — see CLAUDE.md and
 * docs/design). Mirrors Categories\Index's list + inline-create-form
 * pattern; drilling into a country's Regions happens on
 * Admin\Geography\Regions\Index.
 */
class Index extends Component
{
    use WithPagination;

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

    public ?int $editingCountryId = null;

    /** @var array<string, string> */
    public array $editingName = [];

    // ---- Delete confirmation modal ----

    public bool $showDeleteConfirm = false;

    public ?int $deletingCountryId = null;

    /**
     * Columns a staff member is allowed to sort this table by. Name is
     * JSON-per-locale — sorting on it is intentionally left out (same
     * reasoning as Categories\Index).
     */
    protected array $sortable = ['sort_order', 'created_at'];

    public function mount(): void
    {
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

    public function createCountry(): void
    {
        $rules = [];

        foreach (config('ribbon.locales') as $locale) {
            $rules["name.{$locale}"] = ['required', 'string', 'max:120'];
        }

        $this->validate($rules);

        Country::create([
            'name' => $this->name,
            'sort_order' => (int) (Country::max('sort_order') ?? 0) + 1,
        ]);

        $this->resetForm();
        $this->showCreateForm = false;
        $this->resetPage();

        session()->flash('status', 'Country created.');
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

    public function startEdit(int $countryId): void
    {
        $country = Country::findOrFail($countryId);

        $this->editingCountryId = $country->id;
        $this->editingName = collect(config('ribbon.locales'))
            ->mapWithKeys(fn (string $locale) => [$locale => $country->name[$locale] ?? ''])
            ->all();
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->editingCountryId = null;
        $this->editingName = [];
        $this->resetErrorBag();
    }

    public function updateCountry(): void
    {
        if (! $this->editingCountryId) {
            return;
        }

        $rules = [];

        foreach (config('ribbon.locales') as $locale) {
            $rules["editingName.{$locale}"] = ['required', 'string', 'max:120'];
        }

        $this->validate($rules);

        Country::whereKey($this->editingCountryId)->update(['name' => $this->editingName]);

        $this->cancelEdit();

        session()->flash('status', 'Country updated.');
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

    public function confirmDeleteCountry(int $countryId): void
    {
        $this->deletingCountryId = $countryId;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
        $this->deletingCountryId = null;
    }

    #[Computed]
    public function deletingCountry(): ?Country
    {
        if (! $this->deletingCountryId) {
            return null;
        }

        return Country::withCount('regions')->find($this->deletingCountryId);
    }

    #[Computed]
    public function deleteBlockedByRegionsCount(): int
    {
        return $this->deletingCountry?->regions_count ?? 0;
    }

    #[Computed]
    public function deleteBlockedBySellersCount(): int
    {
        if (! $this->deletingCountryId) {
            return 0;
        }

        return Seller::where('country_id', $this->deletingCountryId)->count();
    }

    public function deleteCountry(): void
    {
        // Blocked in the UI with a clear message rather than letting the
        // regions FK (RESTRICT) throw a raw QueryException — a country with
        // any regions is already covered here, and a direct Seller
        // reference is checked too as an explicit, independent guard.
        if ($this->deleteBlockedByRegionsCount > 0 || $this->deleteBlockedBySellersCount > 0) {
            return;
        }

        Country::where('id', $this->deletingCountryId)->delete();

        $this->showDeleteConfirm = false;
        $this->deletingCountryId = null;

        session()->flash('status', 'Country deleted.');
    }

    public function render()
    {
        $sortField = in_array($this->sortField, $this->sortable, true) ? $this->sortField : 'sort_order';
        $defaultLocale = config('ribbon.locales')[0];

        $countries = Country::query()
            ->withCount('regions')
            ->when($this->search !== '', function ($query) use ($defaultLocale) {
                $query->where("name->{$defaultLocale}", 'like', "%{$this->search}%");
            })
            ->orderBy($sortField, $this->sortDirection)
            ->paginate(25);

        return view('livewire.admin.geography.countries.index', [
            'countries' => $countries,
            'defaultLocale' => $defaultLocale,
        ])->layout('layouts.admin', [
            'title' => 'Geography',
            'breadcrumb' => [
                ['label' => 'Geography'],
            ],
        ]);
    }
}
