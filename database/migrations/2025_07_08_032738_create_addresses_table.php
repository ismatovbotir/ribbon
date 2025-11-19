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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained(); // Foreign key to users table
            $table->foreignId('country_id')->constrained(); // Country of the address
            $table->foreignId('region_id')->constrained(); // Region of the address
            $table->foreignId('city_id')->constrained(); // City of the address
            $table->string('address')->nullable(); // Street of the address
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_maps')->nullable(); // Google Maps link or identifier
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
