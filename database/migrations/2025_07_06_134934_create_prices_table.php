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
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained(); // Foreign key to companies table
            $table->foreignUuid('company_id')->constrained();
            $table->foreignId('product_id'); // Foreign key to companies table
            $table->decimal('price', 10, 2); // Price with two decimal places
            $table->integer('qty')->default(1); // Quantity for the price, default is 1
            $table->enum('currency', ['uzs', 'usd'])->default('uzs'); // Currency type, default is UZS
            $table->timestamps();
            $table->unique(['product_id', 'qty'], 'unique_price_per_company_product_currency'); // Ensure unique price per company, product, and currency
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
