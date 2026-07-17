<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(SuperAdminSeeder::class);
        $this->call(GeographySeeder::class);
        $this->call(BrandSeeder::class);
        $this->call(BannerSeeder::class);
        $this->call(ArticleSeeder::class);

        // Fake sellers/categories/products — local dev convenience only.
        // Guarded by environment (not just "not called by default") so it
        // can never fire from a production `db:seed`/`migrate:fresh --seed`
        // even by accident. See DemoCatalogSeeder's own docblock.
        if (app()->environment('local')) {
            $this->call(DemoCatalogSeeder::class);
        }
    }
}
