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
            $table->enum('status', ['pending', 'contacted', 'fulfilled', 'cancelled'])->default('pending');
            $table->timestamps();
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
