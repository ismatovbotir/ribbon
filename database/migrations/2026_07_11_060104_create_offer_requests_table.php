<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('offer_requests', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            // Optional "Who are you?" answer, see OfferRequest::BUYER_TYPES.
            $table->string('buyer_type')->nullable();
            // Where the buyer came from: UTM params + external referrer.
            $table->json('utm')->nullable();
            $table->string('referrer')->nullable();
            $table->enum('status', ['pending', 'contacted', 'fulfilled', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });

        // Line items (product + seller + quantity) are added once the
        // product catalog schema exists — a request spans multiple sellers,
        // so items are what carries the per-seller split, not this header.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_requests');
    }
};
