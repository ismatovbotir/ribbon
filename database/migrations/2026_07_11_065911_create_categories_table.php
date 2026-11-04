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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            // JSON keyed by locale (config('ribbon.locales')), required in all 3 — app-validated.
            $table->json('name');
            // Per-locale URL slug, same shape as name. Per-locale uniqueness
            // is enforced at the app layer (JSON columns can't carry a
            // portable unique constraint on a nested key).
            $table->json('slug');
            $table->boolean('is_active')->default(true);
            // Free vs paid category (CLAUDE.md "Revenue model"): false = anyone
            // may list here for free (Ribbons); true = products are only
            // storefront-visible while the seller's subscription (+ grace)
            // covers today. Admin-controlled; never hard-code a category.
            $table->boolean('requires_subscription')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            // Super Admin-uploaded category illustration. Validated
            // jpg/png, max 1MB, at the UI layer (Livewire's
            // WithFileUploads) — not enforced here.
            $table->string('image_path')->nullable();
            // Mega-menu icon (spec 22): a name from App\Support\Icon::CATEGORY_ICONS,
            // validated in the admin form; null = generic `product` icon.
            $table->string('icon', 40)->nullable();
            // Optional "How to choose" guide shown in the mega menu. Plain
            // column, not a FK: `articles` is created after this table, so
            // the nullOnDelete is done by Article's `deleted` event instead.
            $table->unsignedBigInteger('guide_article_id')->nullable()->index();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Per-locale slug lookups (`where("slug->{$locale}", ...)`) hit this on
        // every storefront page view. SQLite compiles that to
        // json_extract("slug", '$."uz"'), so an expression index on the same
        // expression lets it use an index instead of scanning every row.
        // MySQL/Postgres need a generated column instead — add when switching.
        if (DB::getDriverName() === 'sqlite') {
            foreach (config('ribbon.locales', ['uz', 'ru', 'en']) as $locale) {
                DB::statement("CREATE INDEX categories_slug_{$locale}_index ON categories (json_extract(slug, '$.\"{$locale}\"'))");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
