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
        Schema::create('brands', function (Blueprint $table) {
            // Normal auto-increment id — brands is a small, admin-curated
            // lookup table, unlike products (which use ULIDs).
            $table->id();
            // Plain string, not JSON-per-locale (brand names are proper
            // nouns like "Zebra"/"Honeywell", locale-invariant — matches
            // Product.name per CLAUDE.md's i18n section).
            $table->string('name')->unique();
            // Manufacturer brand logo (e.g. "Zebra"'s own logo), not to be
            // confused with sellers.logo_path (a marketplace vendor's own
            // company identity). Seller-uploaded via Livewire's
            // WithFileUploads; validated jpg/png, max 1MB, at the UI layer.
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
