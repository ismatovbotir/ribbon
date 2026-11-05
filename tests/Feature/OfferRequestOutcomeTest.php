<?php

namespace Tests\Feature;

use App\Livewire\Admin\Offers\Show as AdminOfferShow;
use App\Livewire\Sellers\Requests\Index;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\OfferRequest;
use App\Models\OfferRequestItem;
use App\Models\OfferRequestOutcome;
use App\Models\Product;
use App\Models\Region;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/** Sellers see and answer only their own part of a buyer request. */
class OfferRequestOutcomeTest extends TestCase
{
    use RefreshDatabase;

    private Seller $sellerA;

    private Seller $sellerB;

    private OfferRequest $shared;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->sellerA = $this->makeSeller('a');
        $this->sellerB = $this->makeSeller('b');

        $this->shared = OfferRequest::create(['phone' => '+998901112233', 'company_name' => 'Buyer LLC', 'status' => 'pending']);
        $this->addItem($this->shared, $this->sellerA, 'Alpha Ribbon');
        $this->addItem($this->shared, $this->sellerB, 'Bravo Ribbon');
    }

    private function makeSeller(string $key): Seller
    {
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::create(['name' => $names]);
        $region = Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::create(['region_id' => $region->id, 'name' => $names]);

        $seller = Seller::register(
            ['name' => "Seller {$key}", 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id,
                'city_id' => $city->id, 'vat_number' => "20{$key}", 'phone' => '+998901234567'],
            ['name' => "Owner {$key}", 'email' => "{$key}@example.test", 'password' => 'password', 'locale' => 'en'],
        );
        $seller->forceFill(['status' => 'approved'])->save();

        return $seller;
    }

    private function addItem(OfferRequest $request, Seller $seller, string $name): void
    {
        $brand = Brand::first() ?? Brand::create(['name' => 'No Brand']);
        $category = Category::create([
            'name' => array_fill_keys(config('ribbon.locales'), 'Ribbons'),
            'slug' => array_fill_keys(config('ribbon.locales'), 'c-'.uniqid()),
            'is_active' => true,
            'requires_subscription' => false,
        ]);
        $product = Product::create([
            'seller_id' => $seller->id, 'category_id' => $category->id, 'brand_id' => $brand->id,
            'name' => $name, 'name_extra' => $name, 'slug' => array_fill_keys(config('ribbon.locales'), 'p-'.uniqid()), 'status' => 'approved',
        ]);

        OfferRequestItem::create([
            'offer_request_id' => $request->id, 'product_id' => $product->id, 'seller_id' => $seller->id,
            'unit' => 'pcs', 'quantity' => 2, 'price_at_request' => 1000,
        ]);
    }

    private function owner(Seller $seller): User
    {
        return $seller->users()->firstOrFail();
    }

    public function test_seller_sees_the_buyer_and_only_their_own_items(): void
    {
        Livewire::actingAs($this->owner($this->sellerA))
            ->test(Index::class)
            ->assertSee('+998901112233')
            ->assertSee('Buyer LLC')
            ->assertSee('Alpha Ribbon')
            ->assertDontSee('Bravo Ribbon')
            ->assertDontSee('Seller b');
    }

    public function test_requests_without_this_sellers_items_are_not_listed(): void
    {
        $private = OfferRequest::create(['phone' => '+998907777777', 'status' => 'pending']);
        $this->addItem($private, $this->sellerB, 'Bravo Only');

        Livewire::actingAs($this->owner($this->sellerA))
            ->test(Index::class)
            ->assertDontSee('+998907777777')
            ->assertDontSee('Bravo Only');
    }

    public function test_seller_cannot_mark_a_request_they_are_not_part_of(): void
    {
        $private = OfferRequest::create(['phone' => '+998907777777', 'status' => 'pending']);
        $this->addItem($private, $this->sellerB, 'Bravo Only');

        Livewire::actingAs($this->owner($this->sellerA))
            ->test(Index::class)
            ->call('setOutcome', $private->id, 'won')
            ->assertNotFound();

        $this->assertSame(0, OfferRequestOutcome::count());
    }

    public function test_outcomes_on_a_shared_request_are_independent_per_seller(): void
    {
        Livewire::actingAs($this->owner($this->sellerA))->test(Index::class)->call('setOutcome', $this->shared->id, 'won');
        Livewire::actingAs($this->owner($this->sellerB))->test(Index::class)->call('setOutcome', $this->shared->id, 'lost');

        $this->assertSame('won', OfferRequestOutcome::where('seller_id', $this->sellerA->id)->sole()->outcome);
        $this->assertSame('lost', OfferRequestOutcome::where('seller_id', $this->sellerB->id)->sole()->outcome);
    }

    public function test_employee_can_mark_and_the_actor_is_recorded(): void
    {
        $employee = $this->sellerA->addEmployee(['name' => 'E', 'email' => 'emp@example.test', 'password' => 'password', 'locale' => 'en']);

        Livewire::actingAs($employee)->test(Index::class)->call('setOutcome', $this->shared->id, 'replied');

        $this->assertSame($employee->id, OfferRequestOutcome::sole()->marked_by);
    }

    public function test_changing_an_outcome_keeps_the_first_marked_time(): void
    {
        $owner = $this->owner($this->sellerA);

        $this->travelTo(now()->subDay());
        $first = $this->shared->markOutcome($this->sellerA, 'replied', $owner);
        $firstAt = $first->first_marked_at->toDateTimeString();
        $this->travelBack();

        $this->shared->markOutcome($this->sellerA, 'won', $owner);

        $row = OfferRequestOutcome::sole();
        $this->assertSame('won', $row->outcome);
        $this->assertSame($firstAt, $row->first_marked_at->toDateTimeString());
    }

    public function test_answering_updates_the_new_requests_count(): void
    {
        $this->assertSame(1, OfferRequest::unansweredCountFor($this->sellerA));

        Livewire::actingAs($this->owner($this->sellerA))
            ->test(Index::class)
            ->call('setOutcome', $this->shared->id, 'replied')
            ->assertDispatched('new-requests-count', count: 0);

        $this->assertSame(0, OfferRequest::unansweredCountFor($this->sellerA));
        $this->assertSame(1, OfferRequest::unansweredCountFor($this->sellerB));
    }

    public function test_unknown_outcome_is_refused(): void
    {
        Livewire::actingAs($this->owner($this->sellerA))
            ->test(Index::class)
            ->call('setOutcome', $this->shared->id, 'hacked')
            ->assertStatus(422);

        $this->assertSame(0, OfferRequestOutcome::count());
    }

    public function test_the_model_enforces_the_rules_for_other_front_ends(): void
    {
        $owner = $this->owner($this->sellerA);

        try {
            $this->shared->markOutcome($this->sellerA, 'hacked', $owner);
            $this->fail('Unknown outcome accepted.');
        } catch (InvalidArgumentException) {
        }

        $private = OfferRequest::create(['phone' => '+998907777777', 'status' => 'pending']);
        $this->addItem($private, $this->sellerB, 'Bravo Only');

        $this->expectException(LogicException::class);
        $private->markOutcome($this->sellerA, 'won', $owner);
    }

    public function test_filters_separate_new_from_answered(): void
    {
        $answered = OfferRequest::create(['phone' => '+998905555555', 'status' => 'pending']);
        $this->addItem($answered, $this->sellerA, 'Answered Ribbon');
        $answered->markOutcome($this->sellerA, 'quoted', $this->owner($this->sellerA));

        $component = Livewire::actingAs($this->owner($this->sellerA))->test(Index::class);

        $component->call('setFilter', 'new')->assertSee('Alpha Ribbon')->assertDontSee('Answered Ribbon');
        $component->call('setFilter', 'quoted')->assertSee('Answered Ribbon')->assertDontSee('Alpha Ribbon');
        $component->call('setFilter', 'junk')->assertSet('filter', '')->assertSee('Alpha Ribbon')->assertSee('Answered Ribbon');
    }

    public function test_another_sellers_answer_does_not_hide_a_request_from_new(): void
    {
        $this->shared->markOutcome($this->sellerB, 'won', $this->owner($this->sellerB));

        Livewire::actingAs($this->owner($this->sellerA))
            ->test(Index::class)
            ->call('setFilter', 'new')
            ->assertSee('Alpha Ribbon');
    }

    public function test_page_requires_a_seller_account(): void
    {
        $this->get(route('seller.requests'))->assertRedirect();
        $this->actingAs($this->owner($this->sellerA))->get(route('seller.requests'))->assertOk();
    }

    public function test_super_admin_sees_each_sellers_answer(): void
    {
        $this->shared->markOutcome($this->sellerA, 'won', $this->owner($this->sellerA));

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        Livewire::actingAs($admin)
            ->test(AdminOfferShow::class, ['offerRequest' => $this->shared])
            ->assertSee(__('sellers.requests.outcome.won'))
            ->assertSee(__('admin.offers.no_seller_answer'));
    }
}
