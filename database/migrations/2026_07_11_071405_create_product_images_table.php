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
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            // Seller-uploaded via Livewire's WithFileUploads; validated
            // jpg/png, max 1MB, at the UI layer — not enforced here.
            $table->string('path');
            // The lowest sort_order image is the product's "primary"/cover
            // image by convention — no separate is_primary column. A
            // product may have at most 4 images (see
            // ProductImage::bootProductImage()).
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
