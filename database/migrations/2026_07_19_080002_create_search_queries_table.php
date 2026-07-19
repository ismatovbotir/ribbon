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
        // Append-only event log for what buyers actually typed into
        // storefront search (Storefront\Search) — separate from
        // product_view_events' `search_appearance` rows, which record a
        // *product* showing up in a results grid, not the query text
        // itself. Recorded once per real page load, same guard
        // (`! request()->hasHeader('X-Livewire')`) as that table, for the
        // same reason: a buyer adjusting the category filter within one
        // search shouldn't multiply-count the same query.
        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query');
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamp('occurred_at');
            $table->index(['query', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};
