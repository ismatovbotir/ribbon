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
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            // JSON, keyed by locale (config('ribbon.locales')): {"uz":"...","ru":"...","en":"..."}
            // Admin-authored content is required in all locales — enforce at the app layer.
            $table->json('title');
            $table->string('image_path');
            $table->string('mobile_image_path')->nullable();
            $table->string('link_url')->nullable();
            $table->string('placement');
            // Targets this banner to a specific category (meaningful for
            // `category_top`; ignored for `home_hero`/`home_secondary`).
            // Null = generic/sitewide — valid for any placement, and also a
            // valid "show everywhere" fallback for category_top. Deleting
            // the category only strips targeting, it doesn't delete the
            // banner.
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
