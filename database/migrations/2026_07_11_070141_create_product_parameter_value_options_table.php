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
            $table->foreignId('product_parameter_value_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_parameter_option_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // select_single parameters will only ever have one row per
            // product_parameter_value_id; select_multiple can have several.
            $table->unique(['product_parameter_value_id', 'category_parameter_option_id']);
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
