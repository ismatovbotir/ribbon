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
        Schema::create('product_parameter_values', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_parameter_id')->constrained()->cascadeOnDelete();
            // Only one of these is populated, depending on
            // category_parameters.type — enforced at the app layer:
            // text -> value_text, number -> value_number,
            // select_single/select_multiple -> product_parameter_value_options.
            $table->string('value_text')->nullable();
            $table->decimal('value_number', 12, 3)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'category_parameter_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_parameter_values');
    }
};
