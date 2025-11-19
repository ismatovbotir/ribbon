<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryCompany;
use App\Models\Company;
use App\Models\Country;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $company = Company::create(['ribbon' => true]);
        $company->users()->create([

            'name' => 'Admin',
            'email' => 'info@pos.uz',
            'password' => Hash::make('123456789'),
            'role' => 'admin' // Use a secure password

        ]);
        $company->rates()->create([
            'value' => 12800,
            'user_id' => $company->users->first()->id, // Assuming you want to link rates to users
            'is_global' => true // Assuming you want to track if the rate is global
        ]);
        $category = Category::create([
            'slug' => 'ribbons',

        ]);
        $category->translations()->create([
            'lang' => 'en',
            'name' => 'Ribbons',
            'description' => 'Ribbons are decorative strips of fabric or other material, often used for tying, decorating, or as a symbol of achievement.',

        ]);
        $category->translations()->create([
            'lang' => 'uz',
            'name' => 'Ribbonlar',
            'description' => 'Ribbons are decorative strips of fabric or other material, often used for tying, decorating, or as a symbol of achievement.',

        ]);
        $category->translations()->create([
            'lang' => 'ru',
            'name' => 'Рибоны',
            'description' => 'Ribbons are decorative strips of fabric or other material, often used for tying, decorating, or as a symbol of achievement.',

        ]);
        CategoryCompany::create([
            'category_id' => $category->id,
            'company_id' => $company->id
        ]);
        $country = Country::create([
            'uz' => 'O\'zbekiston',
            'ru' => 'Узбекистан',
            'en' => 'Uzbekistan'
        ]);
        $country->regions()->create([
            'uz' => 'Toshkent',
            'ru' => 'Ташкент',
            'en' => 'Tashkent'
        ]);
        $country->regions()->create([
            'uz' => 'Samarqand',
            'ru' => 'Самарканд',
            'en' => 'Samarkand'
        ]);
        $country->regions()->create([
            'uz' => 'Buxoro',
            'ru' => 'Бухара',
            'en' => 'Bukhara'
        ]);
        $country->regions()->create([
            'uz' => 'Farg\'ona',
            'ru' => 'Фергана',
            'en' => 'Fergana'
        ]);
        $country->regions()->create([
            'uz' => 'Andijon',
            'ru' => 'Андижан',
            'en' => 'Andijan'
        ]);
        $country->regions()->create([
            'uz' => 'Namangan',
            'ru' => 'Наманган',
            'en' => 'Namangan'
        ]);
        $country->regions()->create([
            'uz' => 'Qashqadaryo',
            'ru' => 'Кашкадарья',
            'en' => 'Kashkadarya'
        ]);
        $country->regions()->create([
            'uz' => 'Surxondaryo',
            'ru' => 'Сурхандарья',
            'en' => 'Surkhandarya'
        ]);
        $country->regions()->create([
            'uz' => 'Xorazm',
            'ru' => 'Хорезм',
            'en' => 'Khorezm'
        ]);
    }
}
