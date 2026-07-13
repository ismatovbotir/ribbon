<?php

namespace App\Livewire\Admin\Sellers;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public Seller $seller;

    public bool $showRejectForm = false;

    public string $rejectReason = '';

    public bool $showSuspendForm = false;

    public string $suspendReason = '';

    public function mount(Seller $seller): void
    {
        $this->seller = $seller;
    }

    /**
     * The owner user for this seller company — the one with the seeded
     * `owner` role (as opposed to `employee`) on the seller_user pivot.
     */
    #[Computed]
    public function owner(): ?User
    {
        $ownerRoleId = DB::table('roles')
            ->where('type', 'seller')
            ->where('slug', 'owner')
            ->value('id');

        return $this->seller->users()
            ->wherePivot('role_id', $ownerRoleId)
            ->first();
    }

    /**
     * The admin performing this action. This component only ever renders
     * behind the `admin.auth` middleware (see EnsureAdminIsAuthenticated),
     * which already guarantees Auth::user() is set and holds an admin
     * role — Seller::approve()/reject()/suspend() require a real User to
     * attribute the decision to, and the authenticated admin is that User.
     */
    protected function actingAdmin(): User
    {
        return Auth::user();
    }

    public function approve(): void
    {
        $this->seller->approve($this->actingAdmin());

        session()->flash('status', 'Seller approved.');
    }

    public function openRejectForm(): void
    {
        $this->showRejectForm = true;
    }

    public function cancelReject(): void
    {
        $this->showRejectForm = false;
        $this->rejectReason = '';
        $this->resetErrorBag('rejectReason');
    }

    public function reject(): void
    {
        $this->validate([
            'rejectReason' => ['required', 'string', 'max:1000'],
        ]);

        $this->seller->reject($this->actingAdmin(), $this->rejectReason);

        $this->showRejectForm = false;
        $this->rejectReason = '';

        session()->flash('status', 'Seller rejected.');
    }

    public function openSuspendForm(): void
    {
        $this->showSuspendForm = true;
    }

    public function cancelSuspend(): void
    {
        $this->showSuspendForm = false;
        $this->suspendReason = '';
        $this->resetErrorBag('suspendReason');
    }

    /**
     * Block an already-approved seller. Distinct action from reject() — see
     * Seller::suspend()'s docblock for why these aren't the same thing.
     */
    public function suspend(): void
    {
        $this->validate([
            'suspendReason' => ['required', 'string', 'max:1000'],
        ]);

        $this->seller->suspend($this->actingAdmin(), $this->suspendReason);

        $this->showSuspendForm = false;
        $this->suspendReason = '';

        session()->flash('status', 'Seller blocked.');
    }

    public function render()
    {
        return view('livewire.admin.sellers.show')->layout('layouts.admin', [
            'title' => $this->seller->name,
            'breadcrumb' => [
                ['label' => 'Sellers', 'url' => route('admin.sellers.index')],
                ['label' => $this->seller->name],
            ],
        ]);
    }
}
