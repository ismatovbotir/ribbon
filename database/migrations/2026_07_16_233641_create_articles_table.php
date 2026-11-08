<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            // 'news' = time-sensitive announcements (company/industry news);
            // 'article' = evergreen educational content (history, guides,
            // technical explainers). Storefront filters and badges on this;
            // no cross-type behavior differs beyond that today.
            $table->enum('type', ['news', 'article'])->default('article');
            // JSON, keyed by locale (config('ribbon.locales')): admin-authored
            // content, required in all 3 locales — enforced at the app layer,
            // same convention as categories.name/banners.title.
            $table->json('title');
            // Per-locale URL slug, system-generated from title (never an
            // admin-editable input) — mirrors Category::generateUniqueSlug().
            $table->json('slug');
            // Short teaser shown on the home page section and the /articles
            // list card — optional; falls back to a truncated body on the
            // storefront if left blank.
            $table->json('excerpt')->nullable();
            // Rich HTML per locale, authored via the Trix editor in the
            // admin form (App\Support\HtmlSanitizer strips dangerous
            // elements/attributes before save) and rendered raw on the
            // storefront.
            // A direct answer shown first, for readers and AI agents, plus an
            // optional FAQ: each keyed by locale.
            $table->json('short_answer')->nullable();
            $table->json('faq')->nullable();
            $table->json('body');
            $table->string('cover_image_path')->nullable();
            // Optional tags to catalog categories live in the
            // article_category pivot table (many-to-many — an article can
            // relate to several categories) — see that migration.
            // Null = draft (never shown on the storefront). A real
            // timestamp — even a future one — means published; storefront
            // queries additionally check it's not in the future, matching
            // Banner::isCurrentlyLive()'s scheduling pattern.
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('published_at');
            $table->index(['type', 'published_at']);
        });

        // Per-locale slug lookups (`where("slug->{$locale}", ...)`) hit this on
        // every storefront page view. SQLite compiles that to
        // json_extract("slug", '$."uz"'), so an expression index on the same
        // expression lets it use an index instead of scanning every row.
        // MySQL/Postgres need a generated column instead — add when switching.
        if (DB::getDriverName() === 'sqlite') {
            foreach (config('ribbon.locales', ['uz', 'ru', 'en']) as $locale) {
                DB::statement("CREATE INDEX articles_slug_{$locale}_index ON articles (json_extract(slug, '$.\"{$locale}\"'))");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
