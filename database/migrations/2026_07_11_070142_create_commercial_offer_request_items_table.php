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
        Schema::create('commercial_offer_request_items', function (Blueprint $table) {
            $table->id();
            // Explicit short constraint name: the default
            // `commercial_offer_request_items_commercial_offer_request_id_foreign`
            // is 66 chars, over MySQL's 64-char identifier limit.
            $table->foreignId('commercial_offer_request_id')->constrained(indexName: 'coi_commercial_offer_request_id_foreign')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            // Denormalized alongside product_id rather than joined through
            // it: staff need to see/group the per-seller split directly
            // without an extra join, and a product's seller never changes
            // after creation, so this isn't a normalization risk.
            $table->foreignId('seller_id')->constrained();
            // Matches product_prices.unit — the buyer selects a specific
            // unit/quantity combination when adding an item.
            $table->enum('unit', ['pcs', 'pack', 'box']);
            $table->unsignedInteger('quantity');
            // Snapshot of that unit's price at the moment the buyer
            // submitted the request, since product_prices.price can change
            // later and the historical request should preserve what the
            // buyer actually saw.
            $table->decimal('price_at_request', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commercial_offer_request_items');
    }
};
