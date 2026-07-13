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
        Schema::create('product_parameter_value_options', function (Blueprint $table) {
            $table->id();
            // Both given explicit short constraint names: the defaults
            // (`product_parameter_value_options_product_parameter_value_id_foreign`,
            // `..._category_parameter_option_id_foreign`) are 66/68 chars,
            // over MySQL's 64-char identifier limit.
            $table->foreignId('product_parameter_value_id')->constrained(indexName: 'ppvo_product_parameter_value_id_foreign')->cascadeOnDelete();
            $table->foreignId('category_parameter_option_id')->constrained(indexName: 'ppvo_category_parameter_option_id_foreign')->cascadeOnDelete();
            $table->timestamps();

            // select_single parameters will only ever have one row per
            // product_parameter_value_id; select_multiple can have several.
            // Explicit short index name: the default
            // `product_parameter_value_options_product_parameter_value_id_category_parameter_option_id_unique`
            // is 96 chars, over MySQL's 64-char identifier limit.
            $table->unique(['product_parameter_value_id', 'category_parameter_option_id'], 'ppvo_value_option_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_parameter_value_options');
    }
};
