<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_two_factors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Both encrypted by the model. Recovery codes are also hashed.
            $table->text('secret');
            $table->text('recovery_codes')->nullable();
            // Null until the first valid code proves the app was set up.
            $table->timestamp('confirmed_at')->nullable();
            // Stops a code being replayed within its validity window.
            $table->unsignedBigInteger('last_used_timestep')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_two_factors');
    }
};
