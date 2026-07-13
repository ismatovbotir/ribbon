<?php

namespace App\Livewire\Admin\Offers;

use App\Models\OfferRequest;
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

    public const STATUSES = ['pending', 'contacted', 'fulfilled', 'cancelled'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Toggling the same status again clears the filter, so the quick-filter
     * chips act as a single-select with an "off" state rather than needing
     * a separate "All" reset control (same pattern as Admin\Sellers\Index).
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
        $requests = OfferRequest::query()
            ->withCount('items')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('phone', 'like', "%{$this->search}%")
                        ->orWhere('company_name', 'like', "%{$this->search}%");
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderByDesc('created_at')
            ->paginate(25);

        $statusCounts = OfferRequest::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('livewire.admin.offers.index', [
            'requests' => $requests,
            'statusCounts' => $statusCounts,
            'statuses' => self::STATUSES,
        ])->layout('layouts.admin', [
            'title' => 'Commercial Offers',
            'breadcrumb' => [
                ['label' => 'Commercial Offers'],
            ],
        ]);
    }
}
