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
        Schema::create('category_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            // Stable machine code for parameters that have business rules; translated names can change.
            $table->string('code')->nullable();
            // JSON keyed by locale, required in all 3 — app-validated.
            $table->json('name');
            $table->enum('type', ['text', 'number', 'select_single', 'select_multiple']);
            // Unit label (e.g. "mm", "dpi", "m/s") — only meaningful when type = number.
            $table->string('unit')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'code']);
            $table->index(['category_id', 'sort_order']);
            // Catalog filter sidebar: filterable parameters of one category.
            $table->index(['category_id', 'is_filterable', 'sort_order'], 'category_parameters_filterable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_parameters');
    }
};
