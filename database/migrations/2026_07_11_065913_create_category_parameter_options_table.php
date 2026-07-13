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
        Schema::create('category_parameter_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_parameter_id')->constrained()->cascadeOnDelete();
            // JSON keyed by locale, required in all 3 — app-validated.
            // Only applicable when the parent parameter's type is
            // select_single or select_multiple.
            $table->json('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_parameter_options');
    }
};
