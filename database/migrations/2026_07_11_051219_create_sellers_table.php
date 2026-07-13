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
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address');
            $table->foreignId('country_id')->constrained();
            $table->foreignId('region_id')->constrained();
            $table->foreignId('city_id')->constrained();
            $table->string('vat_number');
            $table->string('phone');
            // The seller company's own logo (distinct from brands.logo_path,
            // which is a product manufacturer brand). Seller-uploaded, may
            // be added later from the dashboard rather than at registration
            // — validated jpg/png, max 1MB, at the UI layer.
            $table->string('logo_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
