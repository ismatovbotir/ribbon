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
        // Buyer-submitted /contact (and, in Phase 2, "Can't find it?")
        // messages. Buyer PII (phone, message): Super Admin only, never
        // logged and never sent to analytics.
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            // contact | chat (corner button) | cant_find (reserved for the Phase 2 demand form)
            $table->string('source', 20)->default('contact');
            $table->string('phone', 32);
            $table->text('message')->nullable();
            $table->string('locale', 2);
            // UTM params + referrer, same capture as offer requests.
            $table->json('utm')->nullable();
            $table->string('referrer')->nullable();
            // Chat button: the page it was sent from, and when Telegram accepted it.
            $table->string('page')->nullable();
            $table->timestamp('telegram_sent_at')->nullable();
            // new | handled
            $table->string('status', 10)->default('new');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
