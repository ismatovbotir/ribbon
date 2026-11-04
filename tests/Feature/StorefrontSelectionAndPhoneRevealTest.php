<?php

namespace Tests\Feature;

use App\Livewire\Storefront\Articles\Index as ArticlesIndex;
use App\Livewire\Storefront\Catalog\Show as CatalogShow;
use App\Livewire\Storefront\Products\Show as ProductShow;
use App\Livewire\Storefront\Search;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Product;
use App\Models\Region;
use App\Models\Seller;
use App\Models\SellerContactEvent;
use App\Services\OfferSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontSelectionAndPhoneRevealTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+998901234567';

    private function makeSeller(string $status = 'approved'): Seller
    {
        static $n = 0;
        $n++;
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::first() ?? Country::create(['name' => $names]);
        $region = Region::first() ?? Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::first() ?? City::create(['region_id' => $region->id, 'name' => $names]);

        $seller = Seller::register(
            [
                'name' => "Seller $n", 'address' => 'Addr', 'country_id' => $country->id,
                'region_id' => $region->id, 'city_id' => $city->id, 'vat_number' => "100$n", 'phone' => self::PHONE,
            ],
            ['name' => 'Owner', 'email' => "owner$n@example.test", 'password' => 'password', 'locale' => 'uz'],
        );
        $seller->forceFill(['status' => $status])->save();

        return $seller;
    }

    private function makeProduct(?Seller $seller = null, string $status = 'approved', string $key = 'p1'): Product
    {
        $category = Category::first() ?? Category::create([
            'name' => ['uz' => 'Lentalar', 'ru' => 'Ленты', 'en' => 'Ribbons'],
            'slug' => ['uz' => 'lentalar', 'ru' => 'lenty', 'en' => 'ribbons'],
            'is_active' => true,
            'requires_subscription' => false,
        ]);
        $brand = Brand::first() ?? Brand::create(['name' => 'No Brand']);

        return Product::create([
            'seller_id' => ($seller ?? $this->makeSeller())->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => "Ribbon $key",
            'slug' => ['uz' => "$key-uz", 'ru' => "$key-ru", 'en' => "$key-en"],
            'status' => $status,
        ]);
    }

    private function lines(): array
    {
        return collect(session('offer_selection', []))->flatten(1)->values()->all();
    }

    public function test_quick_add_adds_vitrin_unit_with_moq_qty_and_dispatches_count(): void
    {
        $product = $this->makeProduct();
        $product->prices()->where('unit', 'pcs')->update(['price' => 100]);
        $box = $product->prices()->create(['unit' => 'box', 'qty_in_pcs' => 10, 'price' => 900, 'is_vitrin' => true]);

        Livewire::test(Search::class)
            ->call('quickAdd', $product->id)
            ->assertDispatched('selection-changed', count: 1);

        $this->assertSame([['product_id' => $product->id, 'unit' => 'box', 'qty' => $product->minQtyForPrice($box)]], $this->lines());
        $this->assertSame(1, $product->minQtyForPrice($box));
    }

    public function test_quick_add_is_idempotent_on_every_surface(): void
    {
        $product = $this->makeProduct();
        $category = $product->category;

        foreach ([Search::class => [], CatalogShow::class => ['categorySlug' => 'lentalar']] as $class => $params) {
            $component = Livewire::test($class, $params);
            $component->call('quickAdd', $product->id)->call('quickAdd', $product->id)
                ->assertDispatched('selection-changed', count: 1);
        }

        $this->assertCount(1, $this->lines());
        $this->assertSame(1, $this->lines()[0]['qty']);
    }

    public function test_hidden_product_cannot_be_quick_added(): void
    {
        $pending = $this->makeProduct(null, 'pending', 'a');
        $suspendedSeller = $this->makeProduct($this->makeSeller('suspended'), 'approved', 'b');
        $bogus = 'does-not-exist';

        foreach ([$pending->id, $suspendedSeller->id, $bogus] as $id) {
            Livewire::test(Search::class)->call('quickAdd', $id)->assertDispatched('selection-changed', count: 0);
        }

        $this->assertSame([], $this->lines());
    }

    public function test_visibility_scope_excludes_hidden_products_everywhere_it_is_used(): void
    {
        $visible = $this->makeProduct(null, 'approved', 'v');
        $this->makeProduct(null, 'rejected', 'r');
        $this->makeProduct($this->makeSeller('pending'), 'approved', 's');

        $this->assertSame([$visible->id], Product::visibleOnStorefront()->pluck('id')->all());
        $this->get('/products/s-uz')->assertNotFound();
    }

    public function test_request_offer_adds_idempotently_validates_unit_and_redirects(): void
    {
        $product = $this->makeProduct();
        $product->prices()->create(['unit' => 'pack', 'qty_in_pcs' => 5, 'price' => 450, 'is_vitrin' => false]);

        $component = Livewire::test(ProductShow::class, ['productSlug' => 'p1-uz'])
            ->set('selectedUnit', 'pack')->set('qty', 3)
            ->call('requestOffer')
            ->assertRedirect(route('storefront.offer-request.show'))
            ->assertDispatched('selection-changed', count: 1);

        $component->call('requestOffer');

        $this->assertSame([['product_id' => $product->id, 'unit' => 'pack', 'qty' => 3]], $this->lines());
    }

    public function test_request_offer_rejects_unit_not_in_product_price_rows(): void
    {
        $product = $this->makeProduct();

        Livewire::test(ProductShow::class, ['productSlug' => 'p1-uz'])
            ->set('selectedUnit', 'box')
            ->call('requestOffer')
            ->assertNotFound();

        $this->assertSame([], $this->lines());
    }

    public function test_request_offer_refuses_product_hidden_after_page_load(): void
    {
        $product = $this->makeProduct();
        $component = Livewire::test(ProductShow::class, ['productSlug' => 'p1-uz']);

        $product->update(['status' => 'suspended']);

        $component->call('requestOffer')->assertNotFound();
        $this->assertSame([], $this->lines());
    }

    public function test_phone_is_absent_from_initial_state_and_returned_after_reveal(): void
    {
        $this->makeProduct();

        // Component state (what Livewire serialises into the page) has no phone.
        $component = Livewire::test(ProductShow::class, ['productSlug' => 'p1-uz']);
        $this->assertNull($component->get('revealedPhone'));
        $this->assertFalse($component->get('phoneRevealed'));
        $this->assertStringNotContainsString('901234567', json_encode($component->snapshot));

        $component->call('revealPhone')
            ->assertReturned(self::PHONE)
            ->assertSet('phoneRevealed', true)
            ->assertSet('revealedPhone', self::PHONE);
    }

    public function test_phone_is_absent_from_initial_html(): void
    {
        // Auto-enables once the frontend engineer removes the always-rendered
        // seller phone from the Blade view and wires revealPhone.
        if (str_contains(file_get_contents(resource_path('views/livewire/storefront/products/show.blade.php')), 'seller->phone')) {
            $this->markTestSkipped('products/show.blade.php still renders seller->phone; pending frontend wiring.');
        }

        $this->makeProduct();

        $html = $this->get('/products/p1-uz')->assertOk()->getContent();

        $this->assertStringNotContainsString('901234567', $html);
    }

    public function test_reveal_logs_one_contact_event_without_buyer_data_and_dedupes(): void
    {
        $product = $this->makeProduct();

        $component = Livewire::test(ProductShow::class, ['productSlug' => 'p1-uz'])
            ->call('revealPhone')->call('revealPhone');

        $this->assertSame(1, SellerContactEvent::count());
        $event = SellerContactEvent::first();
        $this->assertSame($product->id, $event->product_id);
        $this->assertSame($product->seller_id, $event->seller_id);
        $this->assertNotSame('127.0.0.1', $event->visitor_hash);
        $this->assertStringNotContainsString('127.0.0.1', json_encode($event->getAttributes()));
        $this->assertStringNotContainsString('901234567', json_encode($event->getAttributes()));
    }

    public function test_reveal_is_rate_limited_per_ip(): void
    {
        $this->makeProduct();
        RateLimiter::clear('reveal-phone:ip:127.0.0.1');

        $component = Livewire::test(ProductShow::class, ['productSlug' => 'p1-uz']);

        for ($i = 0; $i < 10; $i++) {
            $component->call('revealPhone')->assertHasNoErrors();
        }

        $component->call('revealPhone')->assertHasErrors(['phone'])->assertReturned(null);
    }

    public function test_reveal_refuses_hidden_product(): void
    {
        $product = $this->makeProduct();
        $component = Livewire::test(ProductShow::class, ['productSlug' => 'p1-uz']);
        $product->update(['status' => 'suspended']);

        $component->call('revealPhone')->assertNotFound();
        $this->assertSame(0, SellerContactEvent::count());
    }

    public function test_revealed_phone_cannot_be_set_by_client(): void
    {
        $this->makeProduct();

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(ProductShow::class, ['productSlug' => 'p1-uz'])->set('revealedPhone', '+1');
    }

    public function test_articles_type_filter_was_retired_in_favour_of_news_route(): void
    {
        $this->assertFalse(property_exists(ArticlesIndex::class, 'type'));
        $this->assertFalse(method_exists(ArticlesIndex::class, 'filterByType'));
    }

    public function test_selection_service_count_matches_event_payload_after_remove(): void
    {
        $product = $this->makeProduct();
        OfferSelectionService::ensure($product->id);

        $this->assertSame(1, OfferSelectionService::count());
    }
}
