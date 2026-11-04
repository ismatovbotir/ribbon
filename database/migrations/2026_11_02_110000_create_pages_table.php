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
        // Admin-authored CMS pages. System pages carry an immutable `key`
        // (about, for-sellers, privacy, terms, contact) and live at fixed
        // paths; custom pages have a null key and a per-locale JSON `slug`
        // served at /p/{slug}. Translatable fields are JSON keyed by locale
        // (config('ribbon.locales')), required in all 3 locales at the
        // validation layer — the DB doesn't enforce it. `body` is sanitized
        // (App\Support\HtmlSanitizer) before save.
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->nullable()->unique();
            $table->json('slug')->nullable();
            $table->json('title');
            $table->json('lead')->nullable();
            $table->json('body');
            $table->json('meta_description')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('show_in_header')->default(false);
            $table->boolean('show_in_footer')->default(true);
            // company | legal
            $table->string('footer_column', 10)->default('company');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'show_in_header']);
            $table->index(['is_published', 'show_in_footer', 'footer_column']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
