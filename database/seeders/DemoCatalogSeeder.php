<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\City;
use App\Models\Product;
use App\Models\Region;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * LOCAL-ONLY demo content: 3 approved sellers and approved products with
 * filled parameters, prices and a placeholder image, built on the real
 * catalog structure from CatalogSeeder. DatabaseSeeder only calls this in
 * the `local` environment — it is fake company data and must never reach
 * a real deploy.
 *
 * Deterministic (no random regions/phones/VAT numbers) and idempotent:
 * sellers are keyed on name, products on (seller, category, composed
 * name), so re-running adds nothing new.
 *
 * DatabaseSeeder runs with WithoutModelEvents (so demo sellers don't send
 * a real Telegram "new seller" notification), which also disables
 * Product's auto-slug and auto-pcs-price hooks — this seeder therefore
 * composes name/slug through the same Product::composeNameAndSlug() the
 * seller form uses, and creates the pcs price row itself.
 */
class DemoCatalogSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /** @var array<string, Category> keyed by English category name */
    private array $categories = [];

    public function run(): void
    {
        // Own guard too, so `db:seed --class=DemoCatalogSeeder` can't create
        // demo sellers (password "password") outside local development.
        if (! app()->isLocal()) {
            $this->command?->warn('DemoCatalogSeeder only runs in the local environment — skipped.');

            return;
        }

        $this->categories = Category::with('parameters.options')->get()
            ->keyBy(fn (Category $category) => $category->name['en'])
            ->all();

        if ($this->categories === [] || ! Region::exists()) {
            $this->command?->warn('Run GeographySeeder and CatalogSeeder before DemoCatalogSeeder.');

            return;
        }

        $approver = $this->approver();

        $sellers = [
            $this->seller('Tashkent AutoID MChJ', 'seller1@demo.ribbon.uz', 'Tashkent City', 'Tashkent', '301234567', '+998901234567', $approver),
            $this->seller('Samarqand Barcode Solutions', 'seller2@demo.ribbon.uz', 'Samarqand Region', 'Samarqand', '302345678', '+998912345678', $approver),
            $this->seller('Buxoro Tech Supply', 'seller3@demo.ribbon.uz', 'Bukhara Region', 'Bukhara', '303456789', '+998933456789', $approver),
        ];

        foreach ($this->products() as $index => $spec) {
            $this->product($sellers[$index % count($sellers)], $spec, $approver);
        }

        $this->command?->info('Demo catalog seeded: '.count($sellers).' sellers, '.Product::count().' products.');
    }

    /**
     * Parameter values are keyed by the parameter's English name; select
     * values by the option's English value (array for select_multiple).
     *
     * @return array<int, array{category: string, brand: string, extra?: string, price: int, image: string, values: array<string, mixed>}>
     */
    private function products(): array
    {
        $ribbon = fn (string $type, int $width, int $length, string $side, string $core) => [
            'Type' => $type, 'Width' => $width, 'Length' => $length, 'Ink side' => $side, 'Core' => $core,
            'Notch' => 'No notch', 'TTO' => 'No', 'Colour' => 'Black',
        ];
        $printer = fn (string $dpi, int $width, int $ribbonLength, array $interfaces) => [
            'Resolution' => $dpi, 'Max print width' => $width, 'Ribbon: max width' => $width + 6,
            'Ribbon: max length' => $ribbonLength, 'Ribbon: core' => ['1"'], 'Ribbon: ink side' => ['OUT'],
            'Printhead type' => 'Flat head', 'Interfaces' => $interfaces,
            'Cutter' => 'Optional (sold separately)', 'Peeler' => 'Optional (sold separately)', 'Rewinder' => 'Not available',
        ];

        return [
            ['category' => 'Thermal transfer ribbons', 'brand' => 'Armor', 'extra' => 'AWR 470', 'price' => 45000, 'image' => 'banners/ribbons.jpg', 'values' => $ribbon('Wax', 110, 300, 'OUT', '1"')],
            ['category' => 'Thermal transfer ribbons', 'brand' => 'No Brand', 'price' => 28000, 'image' => 'banners/ribbons.jpg', 'values' => $ribbon('Wax', 60, 300, 'OUT', '1"')],
            ['category' => 'Thermal transfer ribbons', 'brand' => 'Inkanto', 'extra' => 'Premium', 'price' => 52000, 'image' => 'banners/ribbons.jpg', 'values' => $ribbon('Wax-resin', 90, 450, 'OUT', '1"')],
            ['category' => 'Thermal transfer ribbons', 'brand' => 'DNP', 'price' => 98000, 'image' => 'banners/ribbons.jpg', 'values' => $ribbon('Resin', 110, 300, 'IN', '1"')],
            ['category' => 'TT labels', 'brand' => 'No Brand', 'price' => 65000, 'image' => 'banners/ribbons.jpg', 'values' => [
                'Width' => 58, 'Height' => 40, 'Labels per roll' => 1000, 'Gap' => 3, 'Core' => '40 mm',
                'Material' => 'Coated paper (semi-gloss)', 'Adhesive' => 'Permanent', 'Liner colour' => 'White',
            ]],
            ['category' => 'Desktop TT printers', 'brand' => 'Honeywell', 'extra' => 'PC42t', 'price' => 1850000, 'image' => 'banners/honeywell-printers.jpg', 'values' => $printer('203 dpi', 104, 74, ['USB'])],
            ['category' => 'Desktop TT printers', 'brand' => 'TSC', 'extra' => 'TE244', 'price' => 3100000, 'image' => 'banners/honeywell-printers.jpg', 'values' => $printer('203 dpi', 108, 300, ['USB', 'Ethernet'])],
            ['category' => 'Industrial TT printers', 'brand' => 'Zebra', 'extra' => 'ZT411', 'price' => 14500000, 'image' => 'banners/honeywell-printers.jpg', 'values' => $printer('300 dpi', 104, 450, ['USB', 'Ethernet', 'Bluetooth'])],
            ['category' => 'Barcode scanners', 'brand' => 'Zebra', 'extra' => 'DS2208', 'price' => 620000, 'image' => 'banners/zebra-scanners.jpg', 'values' => ['Form factor' => 'Handheld', 'Symbologies' => '2D', 'Interfaces' => ['USB']]],
            ['category' => 'Barcode scanners', 'brand' => 'Honeywell', 'extra' => 'MS7120 Orbit', 'price' => 1450000, 'image' => 'banners/zebra-scanners.jpg', 'values' => ['Form factor' => 'Presentation', 'Symbologies' => '1D', 'Interfaces' => ['USB', 'RS-232']]],
            ['category' => 'PDA / mobile computers', 'brand' => 'Zebra', 'extra' => 'TC21', 'price' => 6800000, 'image' => 'banners/zebra-scanners.jpg', 'values' => ['Operating system' => 'Android', 'Screen size' => 5, 'Scanner' => '2D']],
            ['category' => 'PDA / mobile computers', 'brand' => 'Urovo', 'extra' => 'DT40', 'price' => 4200000, 'image' => 'banners/zebra-scanners.jpg', 'values' => ['Operating system' => 'Android', 'Screen size' => 5, 'Scanner' => '2D']],
            ['category' => 'Label design & printing software', 'brand' => 'Seagull Scientific', 'extra' => 'BarTender', 'price' => 9800000, 'image' => 'banners/honeywell-printers.jpg', 'values' => ['Edition' => 'Professional', 'License type' => 'Perpetual', 'Deployment' => ['Windows desktop'], 'Printers licensed' => 1, 'Features' => ['Database connection'], 'Interface language' => ['Russian', 'English'], 'Delivery' => 'Electronic licence key', 'Support / maintenance included' => 'Yes']],
            ['category' => 'Label design & printing software', 'brand' => 'Loftware', 'extra' => 'NiceLabel', 'price' => 4500000, 'image' => 'banners/honeywell-printers.jpg', 'values' => ['Edition' => 'Designer', 'License type' => 'Subscription (annual)', 'Deployment' => ['Windows desktop', 'Cloud / web'], 'Users / seats' => 1, 'Interface language' => ['Russian', 'English'], 'Delivery' => 'Electronic licence key', 'Support / maintenance included' => 'Yes']],
        ];
    }

    /**
     * The seeded Super Admin approves demo records; null when none was
     * seeded (SUPER_ADMIN_* unset), in which case each seller's owner is
     * used so the demo data doesn't hard-fail.
     */
    private function approver(): ?User
    {
        return User::whereHas('roles', fn ($query) => $query->where('type', 'admin')->where('is_super_admin', true))->first();
    }

    private function seller(string $name, string $email, string $regionEn, string $cityEn, string $vat, string $phone, ?User $approver): Seller
    {
        if ($existing = Seller::where('name', $name)->first()) {
            return $existing;
        }

        $region = Region::where('name->en', $regionEn)->firstOrFail();
        $city = City::where('region_id', $region->id)->where('name->en', $cityEn)->firstOrFail();

        $seller = Seller::register(
            [
                'name' => $name,
                'address' => $city->name['uz'].' shahri, asosiy ombor',
                'country_id' => $region->country_id,
                'region_id' => $region->id,
                'city_id' => $city->id,
                'vat_number' => $vat,
                'phone' => $phone,
            ],
            [
                'name' => $name.' Owner',
                'email' => $email,
                'password' => Hash::make(self::PASSWORD),
                'locale' => 'uz',
            ],
        );

        // Placeholder certificate and B2B info, so the review and product pages show real states.
        $certificate = "seller-certificates/demo-{$seller->id}.pdf";
        Storage::disk('local')->put($certificate, "%PDF-1.4\n% Demo placeholder, not a real document\n");
        $seller->update([
            'registration_certificate_path' => $certificate,
            'is_vat_payer' => true,
            'payment_methods' => ['transfer', 'cash'],
            'warranty_info' => '12 oy kafolat. Toshkentda servis markazi.',
        ]);

        // register() always creates a pending seller — approve so it's buyer-visible.
        $seller->approve($approver ?? $seller->users()->first());

        // Demo subscription states, so every seller-dashboard banner can be
        // tested locally: seller1 active for a year, seller2 expired 3 days
        // ago (in grace, still visible), seller3 expired 30 days ago (lapsed:
        // its paid-category products are hidden, ribbons stay live). Set
        // directly — SubscriptionService requires a Super Admin actor, which
        // may not be seeded. Only on first creation, so manual changes stick.
        $seller->forceFill(['subscription_paid_until' => match ($email) {
            'seller1@demo.ribbon.uz' => now()->addYear(),
            'seller2@demo.ribbon.uz' => now()->subDays(3),
            default => now()->subDays(30),
        }])->save();

        return $seller;
    }

    /**
     * @param  array{category: string, brand: string, extra?: string, price: int, image: string, values: array<string, mixed>}  $spec
     */
    private function product(Seller $seller, array $spec, ?User $approver): void
    {
        $category = $this->categories[$spec['category']];
        $brand = Brand::where('name', $spec['brand'])->firstOrFail();
        $parameters = $category->parameters->sortBy('sort_order')->values();
        $values = $this->resolveValues($parameters, $spec['values']);
        $extra = $spec['extra'] ?? null;

        $label = Product::composeLabel($brand, $parameters, $values, $extra, config('ribbon.locales')[0]);

        if ($seller->products()->where('category_id', $category->id)->where('name', $label)->exists()) {
            return;
        }

        ['name' => $name, 'slug' => $slug] = Product::composeNameAndSlug($brand, $parameters, $values, $extra);

        $product = $seller->products()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => $name,
            'name_extra' => $extra,
            'slug' => $slug,
            'status' => 'approved',
            'moderated_by' => ($approver ?? $seller->users()->first())->id,
            'moderated_at' => now(),
        ]);

        foreach ($parameters as $parameter) {
            $value = $values[$parameter->id] ?? null;

            if ($value === null) {
                continue;
            }

            $row = $product->parameterValues()->create([
                'category_parameter_id' => $parameter->id,
                'value_text' => $parameter->type === 'text' ? $value : null,
                'value_number' => $parameter->type === 'number' ? $value : null,
            ]);

            if (in_array($parameter->type, ['select_single', 'select_multiple'], true)) {
                $row->options()->createMany(
                    collect((array) $value)->map(fn (int $optionId) => ['category_parameter_option_id' => $optionId])->all(),
                );
            }
        }

        $product->prices()->create(['unit' => 'pcs', 'qty_in_pcs' => 1, 'price' => $spec['price'], 'is_vitrin' => true]);
        $product->images()->create(['path' => $spec['image'], 'sort_order' => 0]);
    }

    /**
     * Turn English-keyed demo values into the id-keyed shape the seller
     * form and Product::composeLabel() use: scalar for text/number,
     * option id for select_single, option ids for select_multiple.
     *
     * @param  Collection<int, CategoryParameter>  $parameters
     * @param  array<string, mixed>  $byName
     * @return array<int, mixed>
     */
    private function resolveValues(Collection $parameters, array $byName): array
    {
        $values = [];

        foreach ($parameters as $parameter) {
            $raw = $byName[$parameter->name['en']] ?? null;

            if ($raw === null) {
                continue;
            }

            $optionId = fn (string $en) => $parameter->options->first(fn ($option) => $option->value['en'] === $en)?->id
                ?? throw new \InvalidArgumentException("Unknown option \"{$en}\" for parameter \"{$parameter->name['en']}\".");

            $values[$parameter->id] = match ($parameter->type) {
                'select_single' => $optionId($raw),
                'select_multiple' => array_map($optionId, (array) $raw),
                default => $raw,
            };
        }

        return $values;
    }
}
