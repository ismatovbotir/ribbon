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
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('ribbon')->default(false);
            $table->string('name')->default('MyCompany');
            $table->string('brand')->default('MyBrand');
            $table->foreignId('region_id')->nullable()->constrained()->onDelete('cascade'); // Foreign key to regions table
            $table->foreignId('city_id')->nullable()->constrained()->onDelete('cascade'); // Foreign key to cities table
            $table->string('slug')->unique()->nullable(); // Unique
            $table->string('tax_id')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('logo')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
