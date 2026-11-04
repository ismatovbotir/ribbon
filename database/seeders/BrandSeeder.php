<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Manufacturer brands common in the UZ/CIS auto-ID market. Admin-managed
     * reference data, safe for every environment and idempotent.
     *
     * "No Brand" MUST be created first so it lands at id 1:
     * products.brand_id defaults to 1 (see create_products_table) and
     * Product::composeLabel() omits brand id 1 from titles.
     */
    private const BRANDS = [
        // Printers / printheads
        'Zebra', 'Honeywell', 'TSC', 'Godex', 'Datamax-O\'Neil', 'Citizen', 'Sato', 'Argox', 'Toshiba TEC', 'Printronix',
        // Ribbons / media
        'Armor', 'DNP', 'Ricoh', 'Inkanto', 'Sony',
        // Scanners / mobile computers
        'Datalogic', 'Newland', 'Urovo', 'Mindeo', 'Cipherlab', 'Point Mobile',
        // Label design & printing software vendors (BarTender, NiceLabel, CODESOFT/LABELVIEW)
        'Seagull Scientific', 'Loftware', 'TEKLYNX',
    ];

    public function run(): void
    {
        $noBrand = Brand::firstOrCreate(['name' => 'No Brand']);

        if ($noBrand->id !== 1) {
            $this->command?->warn('"No Brand" is not id 1 — products.brand_id defaults will point at the wrong brand. Re-run on a fresh database.');
        }

        foreach (self::BRANDS as $name) {
            Brand::firstOrCreate(['name' => $name]);
        }
    }
}
