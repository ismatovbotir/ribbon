<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\CategoryParameterOption;
use App\Models\City;
use App\Models\Country;
use App\Models\Product;
use App\Models\Region;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Dev-only demo content: realistic categories/parameters, approved
 * sellers, and approved products with prices + a placeholder image, so
 * the storefront/admin/seller dashboards have something to look at
 * locally. Called from DatabaseSeeder::run() but gated behind
 * `app()->environment('local')` — unlike GeographySeeder/BrandSeeder/
 * BannerSeeder (which the app needs to function at all), this is fake
 * company/product data that must never land in a real deploy, so it's an
 * environment check rather than just "not called by default" (which a
 * copy-pasted deploy seed command could accidentally include).
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $country = Country::first();

        if (! $country) {
            $this->command?->warn('No geography seeded — run GeographySeeder first.');

            return;
        }

        $ribbons = $this->makeCategory('Termotransfer lentalar', 'Термотрансферные ленты', 'Thermal Transfer Ribbons', 1);
        $this->makeSelectParameter($ribbons, 'Turi', 'Тип', 'Type', 1, ['Wax', 'Wax-Resin', 'Resin']);
        $this->makeSelectParameter($ribbons, 'Rangi', 'Цвет', 'Color', 2, ['Qora / Чёрный / Black', 'Ko\'k / Синий / Blue', 'Qizil / Красный / Red']);
        $widthParam = $this->makeNumberParameter($ribbons, 'Kengligi', 'Ширина', 'Width', 3, 'mm');

        $printers = $this->makeCategory('TT Printerlar', 'TT Принтеры', 'TT Printers', 2);
        $this->makeSelectParameter($printers, 'Rezolyutsiya', 'Разрешение', 'Resolution', 1, ['203 dpi', '300 dpi', '600 dpi']);
        $this->makeMultiParameter($printers, 'Ulanish', 'Подключение', 'Connectivity', 2, ['USB', 'Ethernet', 'Wi-Fi', 'Bluetooth']);

        $scanners = $this->makeCategory('Shtrix-kod skanerlari', 'Сканеры штрих-кодов', 'Barcode Scanners', 3);
        $this->makeSelectParameter($scanners, 'Turi', 'Тип', 'Type', 1, ['Qo\'lda / Ручной / Handheld', 'Statsionar / Стационарный / Fixed-mount']);
        $this->makeMultiParameter($scanners, 'Ulanish', 'Подключение', 'Connectivity', 2, ['USB', 'Bluetooth', 'Wireless']);

        $pdas = $this->makeCategory('PDA qurilmalari', 'PDA устройства', 'PDA Devices', 4);
        $this->makeSelectParameter($pdas, 'Operatsion tizim', 'Операционная система', 'Operating System', 1, ['Android', 'Windows']);
        $this->makeNumberParameter($pdas, 'Ekran o\'lchami', 'Размер экрана', 'Screen Size', 2, 'in');

        $sellers = [
            $this->makeSeller('Tashkent AutoID MChJ', 'seller1@demo.ribbon.uz', $country->id),
            $this->makeSeller('Samarqand Barcode Solutions', 'seller2@demo.ribbon.uz', $country->id),
            $this->makeSeller('Buxoro Tech Supply', 'seller3@demo.ribbon.uz', $country->id),
        ];

        $this->makeProducts($ribbons, $sellers, 'banners/ribbons.jpg', [
            ['name' => '110mm x 300m', 'price' => 45000, 'widthMm' => 110],
            ['name' => '60mm x 300m', 'price' => 28000, 'widthMm' => 60],
            ['name' => '90mm x 450m', 'price' => 52000, 'widthMm' => 90],
        ], $widthParam);

        $this->makeProducts($printers, $sellers, 'banners/honeywell-printers.jpg', [
            ['name' => 'PC42t', 'price' => 1850000],
            ['name' => 'ZT230', 'price' => 4200000],
            ['name' => 'TSC TE244', 'price' => 3100000],
        ]);

        $this->makeProducts($scanners, $sellers, 'banners/zebra-scanners.jpg', [
            ['name' => 'DS2208', 'price' => 620000],
            ['name' => 'LS2208', 'price' => 480000],
            ['name' => 'MS7120 Orbit', 'price' => 1450000],
        ]);

        $this->makeProducts($pdas, $sellers, 'banners/zebra-scanners.jpg', [
            ['name' => 'TC21', 'price' => 6800000],
            ['name' => 'MC9300', 'price' => 12500000],
        ]);

        $this->command?->info('Demo catalog seeded: 4 categories, 3 sellers, '.Product::count().' products.');
    }

    private function makeCategory(string $uz, string $ru, string $en, int $sortOrder): Category
    {
        $slug = Str::slug($en);

        return Category::updateOrCreate(
            ['slug->en' => $slug],
            [
                'name' => ['uz' => $uz, 'ru' => $ru, 'en' => $en],
                'slug' => ['uz' => Str::slug($uz), 'ru' => Str::slug($ru), 'en' => $slug],
                'is_active' => true,
                'sort_order' => $sortOrder,
            ],
        );
    }

    private function makeNumberParameter(Category $category, string $uz, string $ru, string $en, int $sortOrder, string $unit): CategoryParameter
    {
        return CategoryParameter::create([
            'category_id' => $category->id,
            'name' => ['uz' => $uz, 'ru' => $ru, 'en' => $en],
            'type' => 'number',
            'unit' => $unit,
            'is_required' => true,
            'is_filterable' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @param  array<int, string>  $optionLabels  "uz / ru / en" per option
     */
    private function makeSelectParameter(Category $category, string $uz, string $ru, string $en, int $sortOrder, array $optionLabels): CategoryParameter
    {
        $parameter = CategoryParameter::create([
            'category_id' => $category->id,
            'name' => ['uz' => $uz, 'ru' => $ru, 'en' => $en],
            'type' => 'select_single',
            'is_required' => true,
            'is_filterable' => true,
            'sort_order' => $sortOrder,
        ]);

        $this->makeOptions($parameter, $optionLabels);

        return $parameter;
    }

    /**
     * @param  array<int, string>  $optionLabels
     */
    private function makeMultiParameter(Category $category, string $uz, string $ru, string $en, int $sortOrder, array $optionLabels): CategoryParameter
    {
        $parameter = CategoryParameter::create([
            'category_id' => $category->id,
            'name' => ['uz' => $uz, 'ru' => $ru, 'en' => $en],
            'type' => 'select_multiple',
            'is_required' => false,
            'is_filterable' => true,
            'sort_order' => $sortOrder,
        ]);

        $this->makeOptions($parameter, $optionLabels);

        return $parameter;
    }

    /**
     * @param  array<int, string>  $optionLabels
     */
    private function makeOptions(CategoryParameter $parameter, array $optionLabels): void
    {
        foreach ($optionLabels as $index => $label) {
            $parts = array_map('trim', explode('/', $label));
            [$uz, $ru, $en] = [$parts[0], $parts[1] ?? $parts[0], $parts[2] ?? $parts[0]];

            CategoryParameterOption::create([
                'category_parameter_id' => $parameter->id,
                'value' => ['uz' => $uz, 'ru' => $ru, 'en' => $en],
                'sort_order' => $index,
            ]);
        }
    }

    private function makeSeller(string $name, string $ownerEmail, int $countryId): Seller
    {
        $existing = Seller::where('name', $name)->first();

        if ($existing) {
            return $existing;
        }

        $region = Region::where('country_id', $countryId)->inRandomOrder()->first();
        $city = City::where('region_id', $region->id)->inRandomOrder()->first();

        $seller = Seller::register(
            [
                'name' => $name,
                'address' => $city->name['uz'].' shahri, asosiy ombor',
                'country_id' => $countryId,
                'region_id' => $region->id,
                'city_id' => $city->id,
                'vat_number' => (string) random_int(100000000, 999999999),
                'phone' => '+998'.random_int(90, 99).random_int(1000000, 9999999),
            ],
            [
                'name' => $name.' Owner',
                'email' => $ownerEmail,
                'password' => Hash::make('password'),
                'locale' => 'uz',
            ],
        );

        // register() leaves every seller `pending` by construction — approve
        // immediately so it's buyer-visible, using the seeded Super Admin as
        // the approving actor (falls back to the owner itself if no Super
        // Admin has been seeded in this environment, just so the demo data
        // doesn't hard-fail).
        $superAdminRoleId = Role::where('type', 'admin')->where('is_super_admin', true)->value('id');
        $superAdminUserId = $superAdminRoleId
            ? DB::table('role_user')->where('role_id', $superAdminRoleId)->value('user_id')
            : null;

        $admin = ($superAdminUserId ? User::find($superAdminUserId) : null)
            ?? $seller->users()->first();

        $seller->approve($admin);

        return $seller;
    }

    /**
     * @param  array<int, Seller>  $sellers
     * @param  array<int, array{name: string, price: int, widthMm?: int}>  $specs
     */
    private function makeProducts(Category $category, array $sellers, string $imagePath, array $specs, ?CategoryParameter $widthParameter = null): void
    {
        foreach ($specs as $index => $spec) {
            $seller = $sellers[$index % count($sellers)];

            if (Product::where('seller_id', $seller->id)->where('category_id', $category->id)->where('name', $spec['name'])->exists()) {
                continue;
            }

            // DatabaseSeeder uses WithoutModelEvents (so demo sellers don't
            // fire a real Telegram "new seller" notification via
            // Seller::bootSeller()) — that also silently disables
            // Product::bootProduct()'s `creating`/`created` hooks, which
            // normally auto-generate `slug` and auto-create the mandatory
            // `pcs` price row. Both are replicated explicitly here rather
            // than relied on.
            $slugs = [];

            foreach (config('ribbon.locales') as $locale) {
                $label = trim(($category->name[$locale] ?? '').' '.$spec['name']);
                $slugs[$locale] = Product::generateUniqueSlug($label, $locale);
            }

            $product = Product::create([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'brand_id' => 1,
                'name' => $spec['name'],
                'slug' => $slugs,
                'status' => 'approved',
            ]);

            if ($widthParameter && isset($spec['widthMm'])) {
                $product->parameterValues()->create([
                    'category_parameter_id' => $widthParameter->id,
                    'value_number' => $spec['widthMm'],
                ]);
            }

            $product->prices()->create([
                'unit' => 'pcs',
                'qty_in_pcs' => 1,
                'price' => $spec['price'],
                'is_vitrin' => true,
            ]);

            $product->images()->create(['path' => $imagePath, 'sort_order' => 0]);
        }
    }
}
