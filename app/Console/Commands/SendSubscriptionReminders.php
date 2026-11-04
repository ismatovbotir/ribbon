<?php

namespace App\Console\Commands;

use App\Jobs\NotifyTelegramOfSubscriptionExpiry;
use App\Models\Seller;
use App\Models\SellerSubscriptionReminder;
use App\Models\Setting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

#[Signature('subscriptions:remind')]
#[Description('Send subscription expiry reminders (expiring soon / grace started); idempotent, run daily.')]
class SendSubscriptionReminders extends Command
{
    public function handle(): int
    {
        $settings = Setting::current();
        $today = today();

        // Window rather than exact-day matching so a missed cron day catches
        // up; the ledger (seller, kind, paid_until) keeps it duplicate-free.
        $expiring = Seller::query()->where('status', 'approved')
            ->whereBetween('subscription_paid_until', [$today->toDateString(), $today->copy()->addDays($settings->reminderDays())->toDateString()])
            ->get()->map(fn ($s) => [$s, 'expiring']);

        $graceStarted = Seller::query()->where('status', 'approved')
            ->whereBetween('subscription_paid_until', [
                $today->copy()->subDays($settings->graceDays())->toDateString(),
                $today->copy()->subDay()->toDateString(),
            ])
            ->get()->map(fn ($s) => [$s, 'grace_started']);

        $sent = 0;

        foreach ($expiring->concat($graceStarted) as [$seller, $kind]) {
            $paidUntil = $seller->subscription_paid_until;

            // Claim the (seller, kind, period) slot first; the unique index
            // makes concurrent/repeated runs lose the race harmlessly.
            try {
                $reminder = SellerSubscriptionReminder::create([
                    'seller_id' => $seller->id,
                    'kind' => $kind,
                    'paid_until' => $paidUntil,
                    'sent_at' => now(),
                ]);
            } catch (QueryException) {
                continue;
            }

            if (NotifyTelegramOfSubscriptionExpiry::dispatchSync($seller, $kind, $paidUntil) === true) {
                $sent++;
            } else {
                // Delivery failed/unconfigured: release the slot so tomorrow retries.
                $reminder->delete();
            }
        }

        $this->info("Subscription reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
