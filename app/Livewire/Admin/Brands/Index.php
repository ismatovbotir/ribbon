<?php

namespace App\Livewire\Admin\Brands;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\Brand;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesAdmin;

    protected function adminAccess(): ?string
    {
        return 'catalog.manage';
    }

    use WithFileUploads, WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $sortField = 'name';

    #[Url(history: true)]
    public string $sortDirection = 'asc';

    public bool $showCreateForm = false;

    public string $name = '';

    /**
     * Staged logo for the create form — jpg/png only, max 1MB (see
     * createBrand()). Stored under the shared `logos/` folder on the
     * `public` disk once saved (also used by Seller.logo_path — Laravel's
     * store() generates collision-proof hashed filenames, so sharing the
     * folder name is safe).
     */
    public $logoUpload = null;

    // ---- Inline row edit ----

    #[Locked]
    public ?int $editingBrandId = null;

    public string $editingName = '';

    public $editingLogoUpload = null;

    public ?string $editingExistingLogoPath = null;

    // ---- Delete confirmation modal ----

    public bool $showDeleteConfirm = false;

    #[Locked]
    public ?int $deletingBrandId = null;

    /**
     * Columns a staff member is allowed to sort this table by.
     */
    protected array $sortable = ['name', 'created_at'];

    public function sortBy(string $field): void
    {
        $this->authorizeAdmin('catalog.manage');

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
        $this->authorizeAdmin('catalog.manage');

        $this->resetPage();
    }

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------

    public function toggleCreateForm(): void
    {
        $this->authorizeAdmin('catalog.manage');

        $this->showCreateForm = ! $this->showCreateForm;

        if (! $this->showCreateForm) {
            $this->resetCreateForm();
        }
    }

    public function createBrand(): void
    {
        $this->authorizeAdmin('catalog.manage');

        $this->validate([
            'name' => ['required', 'string', 'max:120', 'unique:brands,name'],
            'logoUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ]);

        $logoPath = $this->logoUpload ? $this->logoUpload->store('logos', 'public') : null;

        Brand::create([
            'name' => $this->name,
            'logo_path' => $logoPath,
        ]);

        $this->resetCreateForm();
        $this->showCreateForm = false;
        $this->resetPage();

        session()->flash('status', __('admin.ui.brand_created'));
    }

    /**
     * Clears a staged (not-yet-uploaded) logo choice from the create form
     * — there's no "existing" logo to fall back to at this point, so this
     * simply lets the admin start over before submitting.
     */
    public function removeLogoUpload(): void
    {
        $this->authorizeAdmin('catalog.manage');

        $this->logoUpload = null;
    }

    protected function resetCreateForm(): void
    {
        $this->name = '';
        $this->logoUpload = null;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    // ------------------------------------------------------------------
    // Inline edit
    // ------------------------------------------------------------------

    public function startEdit(int $brandId): void
    {
        $this->authorizeAdmin('catalog.manage');

        $brand = Brand::findOrFail($brandId);

        $this->editingBrandId = $brand->id;
        $this->editingName = $brand->name;
        $this->editingLogoUpload = null;
        $this->editingExistingLogoPath = $brand->logo_path;
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->authorizeAdmin('catalog.manage');

        $this->editingBrandId = null;
        $this->editingName = '';
        $this->editingLogoUpload = null;
        $this->editingExistingLogoPath = null;
        $this->resetErrorBag();
    }

    /**
     * Marks the logo for removal on save — distinct from
     * removeLogoUpload()'s create-form equivalent only in that this also
     * has to clear the *existing* persisted path, not just a staged
     * upload.
     */
    public function removeEditingLogo(): void
    {
        $this->authorizeAdmin('catalog.manage');

        $this->editingLogoUpload = null;
        $this->editingExistingLogoPath = null;
    }

    public function updateBrand(): void
    {
        $this->authorizeAdmin('catalog.manage');

        if (! $this->editingBrandId) {
            return;
        }

        $this->validate([
            'editingName' => ['required', 'string', 'max:120', Rule::unique('brands', 'name')->ignore($this->editingBrandId)],
            'editingLogoUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ]);

        $logoPath = $this->editingLogoUpload
            ? $this->editingLogoUpload->store('logos', 'public')
            : $this->editingExistingLogoPath;

        $brand = Brand::findOrFail($this->editingBrandId);
        $brand->update([
            'name' => $this->editingName,
            'logo_path' => $logoPath,
        ]);

        $this->cancelEdit();

        session()->flash('status', __('admin.ui.brand_renamed'));
    }

    // ------------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------------

    public function confirmDeleteBrand(int $brandId): void
    {
        $this->authorizeAdmin('catalog.manage');

        $this->deletingBrandId = $brandId;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->authorizeAdmin('catalog.manage');

        $this->showDeleteConfirm = false;
        $this->deletingBrandId = null;
    }

    #[Computed]
    public function deletingBrand(): ?Brand
    {
        if (! $this->deletingBrandId) {
            return null;
        }

        return Brand::find($this->deletingBrandId);
    }

    #[Computed]
    public function deleteAffectedProductCount(): int
    {
        return $this->deletingBrand?->products()->count() ?? 0;
    }

    public function deleteBrand(): void
    {
        $this->authorizeAdmin('catalog.manage');

        $brand = $this->deletingBrand;

        // "No Brand" is never offered a Delete action in the UI, but guard
        // here too in case of a stale/tampered request — the model itself
        // also throws (see Brand::bootBrand), this just avoids a 500.
        if (! $brand || $brand->name === 'No Brand') {
            $this->cancelDelete();

            return;
        }

        // Products FK's to brands with a plain constrained() (RESTRICT, no
        // cascade) — block in the UI with a clear message rather than
        // letting the delete attempt throw a raw QueryException.
        if ($this->deleteAffectedProductCount > 0) {
            return;
        }

        $brand->delete();

        $this->showDeleteConfirm = false;
        $this->deletingBrandId = null;

        session()->flash('status', __('admin.ui.brand_deleted'));
    }

    public function render()
    {
        $sortField = in_array($this->sortField, $this->sortable, true) ? $this->sortField : 'name';

        $brands = Brand::query()
            ->withCount('products')
            ->when($this->search !== '', function ($query) {
                $query->where('name', 'like', "%{$this->search}%");
            })
            ->orderBy($sortField, $this->sortDirection)
            ->paginate(25);

        return view('livewire.admin.brands.index', [
            'brands' => $brands,
        ])->layout('layouts.admin', [
            'title' => __('admin.ui.brands'),
            'breadcrumb' => [
                ['label' => __('admin.ui.brands')],
            ],
        ]);
    }
}
