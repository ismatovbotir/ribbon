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
        Schema::create('parameter_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parameter_id')->constrained();
            $table->string('lang')->default('en');
            $table->string('name');
            //$table->boolean('default')->default(false); // Default language set to Uzbek
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parameter_translations');
    }
};
