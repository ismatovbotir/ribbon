<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per seller per request: a request can span several sellers,
        // and each one answers only for their own part of it.
        Schema::create('offer_request_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->string('outcome');
            // Never changes after the first tap: the input for response-time ranking.
            $table->timestamp('first_marked_at');
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['offer_request_id', 'seller_id']);
            $table->index(['seller_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_request_outcomes');
    }
};
