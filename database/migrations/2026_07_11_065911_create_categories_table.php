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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            // JSON keyed by locale (config('ribbon.locales')), required in all 3 — app-validated.
            $table->json('name');
            // Per-locale URL slug, same shape as name. Per-locale uniqueness
            // is enforced at the app layer (JSON columns can't carry a
            // portable unique constraint on a nested key).
            $table->json('slug');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            // Super Admin-uploaded category illustration. Validated
            // jpg/png, max 1MB, at the UI layer (Livewire's
            // WithFileUploads) — not enforced here.
            $table->string('image_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
