<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Questions the bot could not match, grouped by normalised text, so staff
     * can teach an answer from real buyer wording. Phone numbers and emails
     * are masked before saving; no buyer identity is stored.
     */
    public function up(): void
    {
        Schema::create('bot_unanswered', function (Blueprint $table) {
            $table->id();
            $table->string('text', 255);
            // sha1 of the normalised text + locale: one row per distinct question.
            $table->char('fingerprint', 40)->unique();
            $table->string('locale', 5);
            $table->string('channel', 20)->default('web');
            $table->unsignedInteger('times')->default(1);
            $table->timestamp('last_asked_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('bot_answer_id')->nullable()->constrained('bot_answers')->nullOnDelete();
            $table->timestamps();

            $table->index(['resolved_at', 'last_asked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_unanswered');
    }
};
