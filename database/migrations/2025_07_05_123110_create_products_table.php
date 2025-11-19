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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->constrained(); // Foreign key to companies table
            //$table->foreignId('item_id')->constrained();
            $table->foreignId('category_id')->constrained(); // Foreign key to categories table
            $table->string('slug')->unique(); // Unique slug for SEO
            $table->string('erp_id')->nullable(); // ERP ID for integration
            $table->string('mark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
