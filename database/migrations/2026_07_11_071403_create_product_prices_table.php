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
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->enum('unit', ['pcs', 'pack', 'box']);
            // How many pcs this unit represents. Always 1 for unit=pcs;
            // seller enters it directly for pack/box (not derived from
            // each other — a box's pcs-count isn't required to be a clean
            // multiple of the pack's).
            $table->unsignedInteger('qty_in_pcs');
            $table->decimal('price', 12, 2);
            // Exactly one row per product should be true — the default
            // price shown on the storefront. Enforced at the app layer
            // (unset the previous vitrin row before setting a new one).
            $table->boolean('is_vitrin')->default(false);
            $table->timestamps();

            // A seller enables whichever units apply to a product — at
            // most one price row per unit.
            $table->unique(['product_id', 'unit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
