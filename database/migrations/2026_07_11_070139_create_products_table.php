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
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained();
            // Defaults to id 1, the seeded "No Brand" placeholder
            // (BrandSeeder) — every product has *some* brand, even when the
            // seller doesn't pick a real one.
            $table->foreignId('brand_id')->default(1)->constrained();
            // Auto-composed, read-only in the seller UI: [brand name, unless
            // "No Brand"] + [each filled category parameter's display value,
            // in sort_order] + [name_extra]. Plain string, not translated
            // (matches the free-text/locale-invariant convention already
            // used for seller-entered brand/model text) — see
            // Product::composeNameAndSlug().
            $table->string('name')->nullable();
            // Small seller-editable suffix appended to the end of the
            // auto-composed `name` (e.g. "Pro Series") — the only part of
            // `name` the seller directly controls. See
            // Product::composeNameAndSlug().
            $table->string('name_extra')->nullable();
            // Machine-derived, SEO-friendly slug, JSON keyed by locale (same
            // pattern as categories.slug) — required in all 3 locales,
            // validated at the app layer. Built per-locale from the same
            // composition as `name` (brand + translated parameter option
            // values + extra), so it changes as specs change — unlike
            // Category's slug, this is intentionally NOT frozen after
            // creation. See Product::composeNameAndSlug() /
            // Product::generateUniqueSlug().
            $table->json('slug');
            // Catalog moderation, mirrors sellers.status.
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            // Catalog page: approved products of one category, newest first.
            $table->index(['category_id', 'status', 'created_at']);
            // Home/search/sitemap (no category) + admin moderation queue.
            $table->index(['status', 'created_at']);
            // Seller dashboard list + storefront scope join through seller.
            $table->index(['seller_id', 'status', 'created_at']);
            $table->index('brand_id');
        });

        // Per-locale slug lookups (`where("slug->{$locale}", ...)`) hit this on
        // every storefront page view. SQLite compiles that to
        // json_extract("slug", '$."uz"'), so an expression index on the same
        // expression lets it use an index instead of scanning every row.
        // MySQL/Postgres need a generated column instead — add when switching.
        if (DB::getDriverName() === 'sqlite') {
            foreach (config('ribbon.locales', ['uz', 'ru', 'en']) as $locale) {
                DB::statement("CREATE INDEX products_slug_{$locale}_index ON products (json_extract(slug, '$.\"{$locale}\"'))");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
