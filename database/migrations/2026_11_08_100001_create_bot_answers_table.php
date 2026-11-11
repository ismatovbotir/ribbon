<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What staff teach the rule-based chat bot (no AI): a reply, the keywords
     * that trigger it and the buttons under it, each per locale. One answer
     * is the start menu. Shared by the website consultant and Telegram.
     */
    public function up(): void
    {
        Schema::create('bot_answers', function (Blueprint $table) {
            $table->id();
            // Stable handle for seeded starter answers (null for staff-made ones).
            $table->string('key', 60)->nullable()->unique();
            // Staff-only label shown in Admin, never to buyers.
            $table->string('name', 120);
            // {"uz": "...", "ru": "...", "en": "..."} plain text, escaped on output.
            $table->json('reply');
            // {"uz": ["narx", ...], "ru": [...], "en": [...]}
            $table->json('keywords')->nullable();
            // [{"action": "answer|finder|category|article|url|lead|handoff|start", "target": "...", "label": {"uz": ...}}]
            $table->json('buttons')->nullable();
            $table->boolean('is_start')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'is_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_answers');
    }
};
