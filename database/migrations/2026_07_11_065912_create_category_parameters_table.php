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
            // JSON keyed by locale, required in all 3 — app-validated.
            $table->json('name');
            $table->enum('type', ['text', 'number', 'select_single', 'select_multiple']);
            // Unit label (e.g. "mm", "dpi", "m/s") — only meaningful when type = number.
            $table->string('unit')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
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
