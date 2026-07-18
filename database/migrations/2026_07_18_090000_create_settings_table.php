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
        // Singleton table — always exactly one row (id 1), created
        // on-demand by Setting::current() rather than seeded, since every
        // column here is optional admin-entered config, not required
        // system data. See App\Models\Setting.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // Tracking snippets, injected into the storefront layout head
            // when set — both left null by default (no tracking without
            // explicit admin opt-in).
            $table->string('google_analytics_id')->nullable();
            $table->string('yandex_metrica_id')->nullable();
            // Site-ownership verification meta tags for Google Search
            // Console / Yandex Webmaster — just the token value, the
            // surrounding <meta> tag is rendered by the layout.
            $table->string('google_site_verification')->nullable();
            $table->string('yandex_site_verification')->nullable();
            // Sitewide contact info, shown in the storefront footer.
            $table->string('admin_phone')->nullable();
            $table->string('admin_email')->nullable();
            // Fallback SEO tags used when a storefront page doesn't supply
            // its own $metaDescription/$ogImage to the layout.
            $table->text('default_meta_description')->nullable();
            $table->string('default_og_image_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
