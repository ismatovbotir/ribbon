<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BannerSeeder extends Seeder
{
    /**
     * Seed 3 sample banners covering the provisional placement slots
     * (Form::PLACEMENTS) and the 3 status states the admin list derives
     * from is_active/starts_at/ends_at (Live now / Scheduled / Expired),
     * so the Banners screen has real, varied data to review. Images were
     * generated as placeholders and live in storage/app/public/banners —
     * run `php artisan storage:link` for them to be publicly reachable.
     */
    public function run(): void
    {
        Banner::updateOrCreate(
            ['image_path' => 'banners/zebra-scanners.jpg'],
            [
                'title' => [
                    'uz' => 'Zebra shtrix-kod skanerlari',
                    'ru' => 'Сканеры штрих-кодов Zebra',
                    'en' => 'Zebra Barcode Scanners',
                ],
                'mobile_image_path' => null,
                'link_url' => null,
                'placement' => 'home_hero',
                'sort_order' => 0,
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
            ],
        );

        Banner::updateOrCreate(
            ['image_path' => 'banners/honeywell-printers.jpg'],
            [
                'title' => [
                    'uz' => 'Honeywell termoprinterlari',
                    'ru' => 'Термопринтеры Honeywell',
                    'en' => 'Honeywell Thermal Transfer Printers',
                ],
                'mobile_image_path' => null,
                'link_url' => null,
                'placement' => 'home_secondary',
                'sort_order' => 1,
                'is_active' => true,
                // Scheduled — hasn't started yet, demonstrates that status
                // state on the admin list.
                'starts_at' => Carbon::now()->addDays(5),
                'ends_at' => null,
            ],
        );

        Banner::updateOrCreate(
            ['image_path' => 'banners/ribbons.jpg'],
            [
                'title' => [
                    'uz' => "Lentalar — o'lcham, rang va turlari",
                    'ru' => 'Ленты — размер, цвет и тип',
                    'en' => 'Ribbons — Every Size, Color & Type',
                ],
                'mobile_image_path' => null,
                'link_url' => null,
                'placement' => 'category_top',
                'sort_order' => 2,
                'is_active' => true,
                // Expired — demonstrates that status state too.
                'starts_at' => null,
                'ends_at' => Carbon::now()->subDay(),
            ],
        );
    }
}
