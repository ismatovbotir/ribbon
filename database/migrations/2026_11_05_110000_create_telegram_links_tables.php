<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A seller user's own Telegram chat. One chat maps to one user, so a
        // button press always identifies exactly who pressed it.
        Schema::create('telegram_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('chat_id')->unique();
            $table->string('username')->nullable();
            $table->timestamps();
        });

        // Short-lived, single-use deep-link tokens. Only the hash is stored.
        Schema::create('telegram_link_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_link_tokens');
        Schema::dropIfExists('telegram_links');
    }
};
