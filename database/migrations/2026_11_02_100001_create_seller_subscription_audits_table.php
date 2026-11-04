<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit trail for every change to sellers.subscription_paid_until
        // (who / when / old / new / why). Append-only; user_id is kept
        // nullable-on-delete so history survives staff removal.
        Schema::create('seller_subscription_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('action', ['payment', 'free_grant', 'set_paid_until']);
            $table->date('old_paid_until')->nullable();
            $table->date('new_paid_until')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->unsignedSmallInteger('months')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['seller_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_subscription_audits');
    }
};
