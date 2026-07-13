<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Seeder;

class GeographySeeder extends Seeder
{
    /**
     * Seeds Uzbekistan's real administrative divisions: the Republic of
     * Karakalpakstan + 12 regions (viloyat) + Tashkent City (its own
     * first-level unit, not part of Tashkent Region) — modeled as 14
     * "regions" so the Country -> Region -> City shape stays uniform rather
     * than special-casing Tashkent City. Each region gets its administrative
     * center as a starter city; this is a starting dataset for sellers to
     * select from, not an exhaustive district/city list.
     */
    public function run(): void
    {
        $country = Country::updateOrCreate(
            ['name->en' => 'Uzbekistan'],
            [
                'name' => [
                    'uz' => "O'zbekiston",
                    'ru' => 'Узбекистан',
                    'en' => 'Uzbekistan',
                ],
                'sort_order' => 0,
            ],
        );

        $regions = [
            [
                'name' => ['uz' => "Qoraqalpog'iston Respublikasi", 'ru' => 'Республика Каракалпакстан', 'en' => 'Republic of Karakalpakstan'],
                'cities' => [
                    ['uz' => 'Nukus', 'ru' => 'Нукус', 'en' => 'Nukus'],
                ],
            ],
            [
                'name' => ['uz' => 'Andijon viloyati', 'ru' => 'Андижанская область', 'en' => 'Andijan Region'],
                'cities' => [
                    ['uz' => 'Andijon', 'ru' => 'Андижан', 'en' => 'Andijan'],
                ],
            ],
            [
                'name' => ['uz' => 'Buxoro viloyati', 'ru' => 'Бухарская область', 'en' => 'Bukhara Region'],
                'cities' => [
                    ['uz' => 'Buxoro', 'ru' => 'Бухара', 'en' => 'Bukhara'],
                ],
            ],
            [
                'name' => ['uz' => "Farg'ona viloyati", 'ru' => 'Ферганская область', 'en' => 'Fergana Region'],
                'cities' => [
                    ['uz' => "Farg'ona", 'ru' => 'Фергана', 'en' => 'Fergana'],
                    ['uz' => "Qo'qon", 'ru' => 'Коканд', 'en' => 'Kokand'],
                    ['uz' => 'Marg\'ilon', 'ru' => 'Маргилан', 'en' => 'Margilan'],
                ],
            ],
            [
                'name' => ['uz' => 'Jizzax viloyati', 'ru' => 'Джизакская область', 'en' => 'Jizzakh Region'],
                'cities' => [
                    ['uz' => 'Jizzax', 'ru' => 'Джизак', 'en' => 'Jizzakh'],
                ],
            ],
            [
                'name' => ['uz' => 'Namangan viloyati', 'ru' => 'Наманганская область', 'en' => 'Namangan Region'],
                'cities' => [
                    ['uz' => 'Namangan', 'ru' => 'Наманган', 'en' => 'Namangan'],
                ],
            ],
            [
                'name' => ['uz' => 'Navoiy viloyati', 'ru' => 'Навоийская область', 'en' => 'Navoiy Region'],
                'cities' => [
                    ['uz' => 'Navoiy', 'ru' => 'Навои', 'en' => 'Navoiy'],
                ],
            ],
            [
                'name' => ['uz' => 'Qashqadaryo viloyati', 'ru' => 'Кашкадарьинская область', 'en' => 'Qashqadaryo Region'],
                'cities' => [
                    ['uz' => 'Qarshi', 'ru' => 'Карши', 'en' => 'Qarshi'],
                    ['uz' => 'Shahrisabz', 'ru' => 'Шахрисабз', 'en' => 'Shahrisabz'],
                ],
            ],
            [
                'name' => ['uz' => 'Samarqand viloyati', 'ru' => 'Самаркандская область', 'en' => 'Samarqand Region'],
                'cities' => [
                    ['uz' => 'Samarqand', 'ru' => 'Самарканд', 'en' => 'Samarqand'],
                    ['uz' => 'Kattaqo\'rg\'on', 'ru' => 'Каттакурган', 'en' => 'Kattaqurgan'],
                ],
            ],
            [
                'name' => ['uz' => 'Sirdaryo viloyati', 'ru' => 'Сырдарьинская область', 'en' => 'Sirdaryo Region'],
                'cities' => [
                    ['uz' => 'Guliston', 'ru' => 'Гулистан', 'en' => 'Guliston'],
                ],
            ],
            [
                'name' => ['uz' => 'Surxondaryo viloyati', 'ru' => 'Сурхандарьинская область', 'en' => 'Surxondaryo Region'],
                'cities' => [
                    ['uz' => 'Termiz', 'ru' => 'Термез', 'en' => 'Termiz'],
                ],
            ],
            [
                'name' => ['uz' => 'Toshkent viloyati', 'ru' => 'Ташкентская область', 'en' => 'Tashkent Region'],
                'cities' => [
                    ['uz' => 'Nurafshon', 'ru' => 'Нурафшан', 'en' => 'Nurafshon'],
                    ['uz' => 'Chirchiq', 'ru' => 'Чирчик', 'en' => 'Chirchiq'],
                    ['uz' => 'Angren', 'ru' => 'Ангрен', 'en' => 'Angren'],
                ],
            ],
            [
                'name' => ['uz' => 'Toshkent shahri', 'ru' => 'город Ташкент', 'en' => 'Tashkent City'],
                'cities' => [
                    ['uz' => 'Toshkent', 'ru' => 'Ташкент', 'en' => 'Tashkent'],
                ],
            ],
        ];

        foreach ($regions as $index => $regionData) {
            $region = Region::updateOrCreate(
                ['country_id' => $country->id, 'name->en' => $regionData['name']['en']],
                [
                    'name' => $regionData['name'],
                    'sort_order' => $index,
                ],
            );

            foreach ($regionData['cities'] as $cityIndex => $cityName) {
                City::updateOrCreate(
                    ['region_id' => $region->id, 'name->en' => $cityName['en']],
                    [
                        'name' => $cityName,
                        'sort_order' => $cityIndex,
                    ],
                );
            }
        }
    }
}
