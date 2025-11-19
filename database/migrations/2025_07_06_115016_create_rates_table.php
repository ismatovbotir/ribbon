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
        Schema::create('rates', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->constrained();
            $table->decimal('value', 10, 2)->default(1);
            $table->foreignUuid('user_id')->constrained(); // Assuming you want to link rates to users
            $table->boolean('is_global')->default(false); // Assuming you want to track if the rate is global
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rates');
    }
};
