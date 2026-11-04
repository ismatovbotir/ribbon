<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only lead log: one row per buyer "Show phone" reveal on a
        // product page. Deliberately holds NO buyer data - no phone, no raw
        // IP, no user agent. `visitor_hash` is a truncated HMAC of
        // (IP + UTC date) keyed by APP_KEY: it rotates daily, cannot be
        // reversed to an IP, and exists only so a refresh/repeat tap by the
        // same visitor on the same product within a day is counted once.
        Schema::create('seller_contact_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['phone_reveal']);
            $table->string('visitor_hash', 32);
            $table->timestamp('occurred_at');
            $table->index(['seller_id', 'type', 'occurred_at']);
            $table->index(['product_id', 'type', 'visitor_hash', 'occurred_at'], 'seller_contact_events_dedupe_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_contact_events');
    }
};
