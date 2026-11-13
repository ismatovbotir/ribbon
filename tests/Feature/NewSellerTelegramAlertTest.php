<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Role;
use App\Models\Seller;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewSellerTelegramAlertTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $company = 'Acme <Labels> & Co'): Seller
    {
        $this->seed(PermissionSeeder::class);
        $this->assertNotNull(Role::where('type', 'seller')->where('slug', 'owner')->first());

        $country = Country::create(['name' => ['uz' => 'O‘zbekiston', 'ru' => 'Узбекистан', 'en' => 'Uzbekistan']]);
        $region = Region::create(['country_id' => $country->id, 'name' => ['uz' => 'T', 'ru' => 'Т', 'en' => 'T']]);
        $city = City::create(['region_id' => $region->id, 'name' => ['uz' => 'Toshkent', 'ru' => 'Ташкент', 'en' => 'Tashkent']]);

        return Seller::register(
            ['name' => $company, 'address' => 'Amir Temur 1', 'country_id' => $country->id, 'region_id' => $region->id, 'city_id' => $city->id, 'vat_number' => '123456789', 'phone' => '+998901234567'],
            ['name' => 'Ali Valiyev', 'email' => 'ali@example.test', 'password' => 'password', 'locale' => 'uz'],
        );
    }

    public function test_the_super_admin_gets_contacts_and_an_approve_link_for_a_new_seller(): void
    {
        config(['services.telegram.bot_token' => 'TEST', 'services.telegram.super_admin_chat_id' => '42', 'app.url' => 'https://ribbon.uz']);
        url()->forceRootUrl('https://ribbon.uz');
        url()->forceScheme('https');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $seller = $this->register();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($seller) {
            $text = $request['text'];

            return $request['chat_id'] === '42'
                && str_contains($text, 'Acme &lt;Labels&gt; &amp; Co') // escaped, so Telegram accepts it
                && str_contains($text, '123456789')
                && str_contains($text, '+998901234567')
                && str_contains($text, 'Ташкент, Amir Temur 1')
                && str_contains($text, 'Ali Valiyev (ali@example.test)') // owner is attached before sending
                && $request['reply_markup']['inline_keyboard'][0][0]['url'] === 'https://ribbon.uz/admin/sellers/'.$seller->id;
        });
    }

    public function test_without_https_the_link_goes_in_the_text_instead_of_a_button(): void
    {
        config(['services.telegram.bot_token' => 'TEST', 'services.telegram.super_admin_chat_id' => '42']);
        url()->forceRootUrl('http://127.0.0.1:8000');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $seller = $this->register('Plain');

        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'http://127.0.0.1:8000/admin/sellers/'.$seller->id)
            && ! isset($request['reply_markup']));
    }

    public function test_nothing_is_sent_without_a_chat_id(): void
    {
        config(['services.telegram.bot_token' => 'TEST', 'services.telegram.super_admin_chat_id' => null]);
        Http::fake();

        $this->register('Quiet');

        Http::assertNothingSent();
    }
}
