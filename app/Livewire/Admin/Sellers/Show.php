<?php

namespace App\Livewire\Admin\Sellers;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesAdmin;

    protected function adminAccess(): ?string
    {
        return 'sellers.moderate';
    }

    // Seller is bound by route model binding in mount() and never taken
    // from the client afterwards.
    #[Locked]
    public Seller $seller;

    public bool $showRejectForm = false;

    public string $rejectReason = '';

    public bool $showSuspendForm = false;

    public string $suspendReason = '';

    public function mount(Seller $seller): void
    {
        $this->authorizeAdmin('sellers.moderate');

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
        $this->authorizeAdmin('sellers.moderate');

        if (! $this->seller->hasCertificate()) {
            $this->addError('approve', __('admin.sellers.certificate_required_to_approve'));

            return;
        }

        $this->seller->approve($this->actingAdmin());

        session()->flash('status', __('admin.ui.seller_approved'));
    }

    public function markCertificateReviewed(): void
    {
        $this->authorizeAdmin('sellers.moderate');

        $this->seller->markCertificateReviewed($this->actingAdmin());

        session()->flash('status', __('admin.sellers.certificate_reviewed'));
    }

    public function openRejectForm(): void
    {
        $this->authorizeAdmin('sellers.moderate');

        $this->showRejectForm = true;
    }

    public function cancelReject(): void
    {
        $this->authorizeAdmin('sellers.moderate');

        $this->showRejectForm = false;
        $this->rejectReason = '';
        $this->resetErrorBag('rejectReason');
    }

    public function reject(): void
    {
        $this->authorizeAdmin('sellers.moderate');

        $this->validate([
            'rejectReason' => ['required', 'string', 'max:1000'],
        ]);

        $this->seller->reject($this->actingAdmin(), $this->rejectReason);

        $this->showRejectForm = false;
        $this->rejectReason = '';

        session()->flash('status', __('admin.ui.seller_rejected'));
    }

    public function openSuspendForm(): void
    {
        $this->authorizeAdmin('sellers.moderate');

        $this->showSuspendForm = true;
    }

    public function cancelSuspend(): void
    {
        $this->authorizeAdmin('sellers.moderate');

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
        $this->authorizeAdmin('sellers.moderate');

        $this->validate([
            'suspendReason' => ['required', 'string', 'max:1000'],
        ]);

        $this->seller->suspend($this->actingAdmin(), $this->suspendReason);

        $this->showSuspendForm = false;
        $this->suspendReason = '';

        session()->flash('status', __('admin.ui.seller_blocked'));
    }

    // ---- Subscription (Super Admin only: `manage-subscriptions` gate) ----

    /** '' | 'payment' | 'free' | 'correct' - which form drawer is open. */
    public string $subscriptionDrawer = '';

    public ?string $paymentAmount = null;

    public ?string $paymentMonths = '1';

    public ?string $paymentNote = null;

    public ?string $freeMonths = '1';

    public ?string $freeNote = null;

    public ?string $correctDate = null;

    public string $correctReason = '';

    protected function canManageSubscriptions(): bool
    {
        return Gate::allows('manage-subscriptions');
    }

    /** @return array<string, mixed>|null null for users who may not see it. */
    #[Computed]
    public function subscription(): ?array
    {
        if (! $this->canManageSubscriptions()) {
            return null;
        }

        return app(SubscriptionService::class)->statusFor($this->seller);
    }

    #[Computed]
    public function subscriptionPayments()
    {
        if (! $this->canManageSubscriptions()) {
            return collect();
        }

        return $this->seller->subscriptionPayments()->with('recordedBy:id,name')->latest('id')->limit(50)->get();
    }

    #[Computed]
    public function subscriptionAudits()
    {
        if (! $this->canManageSubscriptions()) {
            return collect();
        }

        return $this->seller->subscriptionAudits()->with('user:id,name')->latest('id')->limit(50)->get();
    }

    public function openSubscriptionDrawer(string $kind): void
    {
        $this->authorizeSuperAdmin();

        abort_unless(in_array($kind, ['payment', 'free', 'correct'], true), 404);

        $this->resetErrorBag();
        $this->paymentAmount = Setting::current()->subscription_price_uzs !== null
            ? (string) Setting::current()->subscription_price_uzs
            : null;
        $this->paymentMonths = '1';
        $this->paymentNote = null;
        $this->freeMonths = '1';
        $this->freeNote = null;
        $this->correctDate = $this->seller->subscription_paid_until?->toDateString();
        $this->correctReason = '';
        $this->subscriptionDrawer = $kind;
    }

    public function closeSubscriptionDrawer(): void
    {
        $this->authorizeSuperAdmin();

        $this->subscriptionDrawer = '';
        $this->resetErrorBag();
    }

    public function recordPayment(SubscriptionService $subscriptions): void
    {
        $this->authorizeSuperAdmin();

        $this->validate([
            'paymentAmount' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'paymentMonths' => ['required', 'integer', 'min:1', 'max:12'],
            'paymentNote' => ['nullable', 'string', 'max:500'],
        ]);

        $subscriptions->recordPayment(
            $this->seller,
            $this->actingAdmin(),
            (int) $this->paymentAmount,
            (int) $this->paymentMonths,
            filled($this->paymentNote) ? $this->paymentNote : null,
        );

        $this->afterSubscriptionChange('payment');
    }

    public function grantFreeMonths(SubscriptionService $subscriptions): void
    {
        $this->authorizeSuperAdmin();

        $this->validate([
            'freeMonths' => ['required', 'integer', 'min:1', 'max:12'],
            'freeNote' => ['nullable', 'string', 'max:500'],
        ]);

        $subscriptions->grantFreeMonths(
            $this->seller,
            $this->actingAdmin(),
            (int) $this->freeMonths,
            filled($this->freeNote) ? $this->freeNote : null,
        );

        $this->afterSubscriptionChange('free');
    }

    public function correctPaidUntil(SubscriptionService $subscriptions): void
    {
        $this->authorizeSuperAdmin();

        $this->validate([
            'correctDate' => ['nullable', 'date_format:Y-m-d'],
            'correctReason' => ['required', 'string', 'max:500'],
        ]);

        $subscriptions->setPaidUntil(
            $this->seller,
            $this->actingAdmin(),
            filled($this->correctDate) ? Carbon::parse($this->correctDate) : null,
            $this->correctReason,
        );

        $this->afterSubscriptionChange('corrected');
    }

    protected function afterSubscriptionChange(string $flash): void
    {
        $this->seller->refresh();
        $this->subscriptionDrawer = '';
        unset($this->subscription, $this->subscriptionPayments, $this->subscriptionAudits);

        session()->flash('status', __('admin.subscription.flash.'.$flash));
    }

    public function render()
    {
        return view('livewire.admin.sellers.show')->layout('layouts.admin', [
            'title' => $this->seller->name,
            'breadcrumb' => [
                ['label' => __('admin.ui.sellers'), 'url' => route('admin.sellers.index')],
                ['label' => $this->seller->name],
            ],
        ]);
    }
}
