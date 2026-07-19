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
        // One row per Telegram user who has ever messaged the bot — created
        // on first incoming webhook message (see TelegramWebhookController),
        // never admin-created. Telegram chat IDs for a private one-on-one
        // bot chat are always positive, but signed bigInteger costs nothing
        // extra and avoids ever having to reconsider this if a group-chat
        // use case shows up later.
        Schema::create('telegram_contacts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id')->unique();
            // All from Telegram's own `from` object on the first message —
            // username is genuinely optional on Telegram (not every user
            // sets one), first_name is not.
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_contacts');
    }
};
