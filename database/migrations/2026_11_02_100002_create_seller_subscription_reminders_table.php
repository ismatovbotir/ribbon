<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotency ledger for `subscriptions:remind`: one row per
        // (seller, kind, paid_until period), so a daily cron that runs twice
        // - or catches up after a missed day - never sends duplicates.
        // A renewal changes paid_until, which naturally opens a new period.
        Schema::create('seller_subscription_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['expiring', 'grace_started']);
            $table->date('paid_until');
            $table->timestamp('sent_at');

            $table->unique(['seller_id', 'kind', 'paid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_subscription_reminders');
    }
};
