<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only: who changed a category's free/paid flag, and when. The
        // flag decides which sellers must pay, so it's a subscription change.
        Schema::create('category_subscription_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('old_requires_subscription');
            $table->boolean('new_requires_subscription');
            $table->timestamp('created_at')->nullable();

            $table->index(['category_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_subscription_audits');
    }
};
