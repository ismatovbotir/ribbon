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
        // Append-only event log for seller-facing product analytics (see
        // App\Services\ProductAnalyticsService) — no updated_at, rows are
        // never mutated after insert. `seller_id` is denormalized alongside
        // product_id, same reasoning as commercial_offer_request_items: the
        // seller-scoped dashboard queries by seller_id directly without a
        // join through products, and a product's seller never changes.
        Schema::create('product_view_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            // `view` = a buyer opened this product's detail page.
            // `search_appearance` = this product was rendered in a catalog/
            // search/home results grid (an impression), recorded once per
            // real page load — not on every Livewire re-render a filter or
            // pagination click triggers within that same visit. See
            // ProductAnalyticsService::recordSearchAppearances().
            $table->enum('type', ['view', 'search_appearance']);
            // Referrer-derived traffic source — only meaningful for `view`
            // events (a buyer arriving at a product page from somewhere);
            // `search_appearance` rows always leave this null, since "where
            // did this impression come from" isn't a meaningful question
            // for a grid the buyer is already browsing within Ribbon.
            $table->enum('source', ['direct', 'google', 'yandex', 'internal_search', 'other'])->nullable();
            $table->timestamp('occurred_at');
            $table->index(['seller_id', 'type', 'occurred_at']);
            $table->index(['product_id', 'type', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_view_events');
    }
};
