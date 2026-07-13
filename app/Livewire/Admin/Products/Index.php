<?php

namespace App\Livewire\Admin\Products;

use App\Models\Product;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $sortField = 'created_at';

    #[Url(history: true)]
    public string $sortDirection = 'desc';

    public const STATUSES = ['pending', 'approved', 'rejected', 'suspended'];

    /**
     * Columns a staff member is allowed to sort this table by.
     *
     * @var array<int, string>
     */
    protected array $sortable = ['name', 'status', 'created_at'];

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
     * Toggling the same status again clears the filter, so the quick-filter
     * chips act as a single-select with an "off" state rather than needing
     * a separate "All" reset control.
     */
    public function filterByStatus(string $status): void
    {
        $this->status = $this->status === $status ? '' : $status;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->resetPage();
    }

    public function render()
    {
        $sortField = in_array($this->sortField, $this->sortable, true) ? $this->sortField : 'created_at';

        $products = Product::query()
            ->with(['category', 'seller', 'brand'])
            ->when($this->search !== '', function ($query) {
                $query->where('name', 'like', "%{$this->search}%");
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderBy($sortField, $this->sortDirection)
            ->paginate(25);

        $statusCounts = Product::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('livewire.admin.products.index', [
            'products' => $products,
            'statusCounts' => $statusCounts,
            'statuses' => self::STATUSES,
            'defaultLocale' => config('ribbon.locales')[0],
        ])->layout('layouts.admin', [
            'title' => 'Products',
            'breadcrumb' => [
                ['label' => 'Products'],
            ],
        ]);
    }
}
