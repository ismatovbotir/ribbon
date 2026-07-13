<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Seed the "No Brand" placeholder brand, which must land at id 1 —
     * products.brand_id defaults to 1 (see the create_products_table
     * migration) for products the seller didn't assign a real brand to.
     * Idempotent, matching BannerSeeder's updateOrCreate() style.
     */
    public function run(): void
    {
        Brand::updateOrCreate(['name' => 'No Brand'], []);
    }
}
