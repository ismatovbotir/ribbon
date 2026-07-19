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
        // Append-only conversation log per contact — both directions live
        // in one table (distinguished by `direction`) so the admin inbox
        // thread view is a single ordered query, not a union of two
        // tables.
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_contact_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->text('body');
            // Telegram's own message_id for this send/receive — not used
            // for anything yet beyond a debugging breadcrumb, but free to
            // capture at insert time either way.
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            // Only set for `out` — which admin actually sent this reply.
            // Nullable rather than required since an admin account could
            // be deleted later without invalidating message history.
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['telegram_contact_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
