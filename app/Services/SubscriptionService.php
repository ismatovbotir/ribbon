<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerSubscriptionAudit;
use App\Models\SellerSubscriptionPayment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Seller subscription rules (manual monthly billing, CLAUDE.md "Revenue
 * model"). Mutations are Super Admin only: they authorize the acting user
 * via the `manage-subscriptions` gate themselves, so every front end
 * (Livewire, Telegram Web App, console) gets the same protection.
 *
 * Status helpers are pure date math on sellers.subscription_paid_until;
 * storefront visibility is a query scope (Product::scopeVisibleOnStorefront)
 * that applies the same grace rule - nothing here ever changes a product or
 * seller status.
 */
class SubscriptionService
{
    public const STATUS_NONE = 'none';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_GRACE = 'grace';

    public const STATUS_LAPSED = 'lapsed';

    public function graceDays(): int
    {
        return Setting::current()->graceDays();
    }

    /**
     * Record a manual payment: extends paid_until from
     * max(today, current paid_until) by $months and writes the payment row
     * plus an audit row, atomically.
     */
    public function recordPayment(Seller $seller, User $admin, int $amount, int $months = 1, ?string $note = null): SellerSubscriptionPayment
    {
        if ($amount < 0 || $months < 1) {
            throw new InvalidArgumentException('Amount must be >= 0 and months >= 1.');
        }

        return $this->extend($seller, $admin, $amount, $months, $note, 'payment');
    }

    /**
     * Cold-start "first N months free": a payment row with amount 0.
     */
    public function grantFreeMonths(Seller $seller, User $admin, int $months, ?string $note = null): SellerSubscriptionPayment
    {
        if ($months < 1) {
            throw new InvalidArgumentException('Months must be >= 1.');
        }

        return $this->extend($seller, $admin, 0, $months, $note, 'free_grant');
    }

    /**
     * Manual correction of paid_until (null clears it). Audited; the reason
     * is mandatory. Does not create a payment row.
     */
    public function setPaidUntil(Seller $seller, User $admin, ?Carbon $date, string $reason): Seller
    {
        Gate::forUser($admin)->authorize('manage-subscriptions');

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required.');
        }

        return DB::transaction(function () use ($seller, $admin, $date, $reason) {
            $locked = Seller::query()->lockForUpdate()->findOrFail($seller->id);
            $old = $locked->subscription_paid_until;

            $locked->subscription_paid_until = $date?->copy()->startOfDay();
            $locked->save();

            $this->audit($locked, $admin, 'set_paid_until', $old, $locked->subscription_paid_until, null, null, $reason);

            $seller->setRawAttributes($locked->getAttributes(), true);

            return $seller;
        });
    }

    /** paid_until >= today. */
    public function isActive(Seller $seller): bool
    {
        return $seller->subscription_paid_until !== null
            && $seller->subscription_paid_until->gte(today());
    }

    /** Expired, but still within the grace days. */
    public function inGrace(Seller $seller): bool
    {
        return $seller->subscription_paid_until !== null
            && ! $this->isActive($seller)
            && $seller->subscription_paid_until->copy()->addDays($this->graceDays())->gte(today());
    }

    /**
     * Whether the seller may add new products in this category. Free
     * categories are always open. Paid ones are open while the subscription
     * is active or in grace, the same cutoff as
     * Product::scopeCoveredBySubscription(), so whatever a seller can add is
     * also visible to buyers. Existing products in a locked category stay
     * editable (CLAUDE.md); only creating new ones is blocked.
     */
    public function canListIn(Seller $seller, Category $category): bool
    {
        return ! $category->requires_subscription || ! $this->isLapsed($seller);
    }

    /** Never paid, or past the grace period. */
    public function isLapsed(Seller $seller): bool
    {
        return ! $this->isActive($seller) && ! $this->inGrace($seller);
    }

    /**
     * Whole days until paid_until (0 = last paid day, negative = expired
     * that many days ago); null if never paid.
     */
    public function daysLeft(Seller $seller): ?int
    {
        if ($seller->subscription_paid_until === null) {
            return null;
        }

        return (int) today()->diffInDays($seller->subscription_paid_until->copy()->startOfDay(), false);
    }

    /**
     * Read-only data for the seller dashboard / Super Admin screen.
     *
     * @return array{status: string, paid_until: ?Carbon, days_left: ?int, grace_days: int, grace_ends_at: ?Carbon, in_grace: bool, hidden_products_count: int}
     */
    public function statusFor(Seller $seller): array
    {
        $paidUntil = $seller->subscription_paid_until;

        $status = match (true) {
            $paidUntil === null => self::STATUS_NONE,
            $this->isActive($seller) => self::STATUS_ACTIVE,
            $this->inGrace($seller) => self::STATUS_GRACE,
            default => self::STATUS_LAPSED,
        };

        return [
            'status' => $status,
            'paid_until' => $paidUntil,
            'days_left' => $this->daysLeft($seller),
            'grace_days' => $this->graceDays(),
            'grace_ends_at' => $paidUntil?->copy()->addDays($this->graceDays()),
            'in_grace' => $status === self::STATUS_GRACE,
            'hidden_products_count' => $this->hiddenProductsCount($seller),
        ];
    }

    /** Approved products of this seller hidden from the storefront only because of the subscription. */
    public function hiddenProductsCount(Seller $seller): int
    {
        return Product::query()
            ->where('seller_id', $seller->id)
            ->hiddenBySubscription()
            ->count();
    }

    private function extend(Seller $seller, User $admin, int $amount, int $months, ?string $note, string $action): SellerSubscriptionPayment
    {
        Gate::forUser($admin)->authorize('manage-subscriptions');

        return DB::transaction(function () use ($seller, $admin, $amount, $months, $note, $action) {
            $locked = Seller::query()->lockForUpdate()->findOrFail($seller->id);
            $old = $locked->subscription_paid_until;

            $start = ($old !== null && $old->gt(today())) ? $old->copy()->startOfDay() : today();
            $end = $start->copy()->addMonthsNoOverflow($months);

            $locked->subscription_paid_until = $end;
            $locked->save();

            $payment = SellerSubscriptionPayment::create([
                'seller_id' => $locked->id,
                'amount' => $amount,
                'months' => $months,
                'period_start' => $start,
                'period_end' => $end,
                'recorded_by' => $admin->id,
                'note' => $note,
            ]);

            $this->audit($locked, $admin, $action, $old, $end, $amount, $months, $note);

            $seller->setRawAttributes($locked->getAttributes(), true);

            return $payment;
        });
    }

    private function audit(Seller $seller, User $admin, string $action, ?Carbon $old, ?Carbon $new, ?int $amount, ?int $months, ?string $note): void
    {
        SellerSubscriptionAudit::create([
            'seller_id' => $seller->id,
            'user_id' => $admin->id,
            'action' => $action,
            'old_paid_until' => $old,
            'new_paid_until' => $new,
            'amount' => $amount,
            'months' => $months,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
