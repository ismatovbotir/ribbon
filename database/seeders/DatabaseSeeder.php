<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    // Keeps seeding from firing side effects such as Telegram notifications
    // for new sellers/requests. Seeders that need a model hook's behaviour
    // (e.g. Product's slug/pcs price) replicate it explicitly.
    use WithoutModelEvents;

    public function run(): void
    {
        // One transaction: SQLite commits per statement otherwise, which
        // makes a full seed many times slower, and a failure half-way
        // leaves nothing behind.
        DB::transaction(function () {
            // Reference data — required in every environment, idempotent.
            $this->call([
                PermissionSeeder::class,
                SuperAdminSeeder::class,
                GeographySeeder::class,
                BrandSeeder::class,      // "No Brand" must land at id 1
                CatalogSeeder::class,
                PageSeeder::class,
                ArticleSeeder::class,
                BotAnswerSeeder::class,  // after articles: links the guide; bot stays off until staff switch it on
            ]);

            // Fake data — local development only, never a real deploy.
            if (app()->environment('local')) {
                $this->call([
                    BannerSeeder::class,
                    DemoCatalogSeeder::class,
                    DemoStaffSeeder::class,  // admin@ / moderator@ / employee@demo.ribbon.uz
                ]);
            }
        });
    }
}
