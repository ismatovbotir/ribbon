<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // History of manual (bank transfer / invoice) subscription payments
        // and free grants (amount 0). Append-only.
        Schema::create('seller_subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount'); // UZS, 0 = free grant
            $table->unsignedSmallInteger('months')->default(1);
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_subscription_payments');
    }
};
