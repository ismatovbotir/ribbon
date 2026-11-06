<?php

namespace Tests\Feature;

use App\Livewire\Admin\Categories\Index as CategoriesIndex;
use App\Livewire\Admin\Categories\Show as CategoriesShow;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Storefront\OfferRequest\Show as OfferRequestShow;
use App\Livewire\Storefront\Products\Show as ProductShow;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\OfferRequest;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Region;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Models\UserTwoFactor;
use App\Services\OfferSelectionService;
use App\Services\TwoFactorService;
use App\Support\HtmlSanitizer;
use App\Support\UploadLimit;
use Carbon\Carbon;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** Regressions from launch QA pass #5. */
class LaunchQaFixesTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->category = Category::create([
            'name' => array_fill_keys(config('ribbon.locales'), 'Ribbons'),
            'slug' => array_fill_keys(config('ribbon.locales'), 'ribbons'),
            'is_active' => true,
            'requires_subscription' => false,
        ]);
    }

    private function product(int $moq): Product
    {
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::create(['name' => $names]);
        $region = Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::create(['region_id' => $region->id, 'name' => $names]);
        $seller = Seller::register(
            ['name' => 'S', 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id,
                'city_id' => $city->id, 'vat_number' => '200123456', 'phone' => '+998901234567'],
            ['name' => 'O', 'email' => 'o@example.test', 'password' => 'password', 'locale' => 'en'],
        );
        $seller->forceFill(['status' => 'approved'])->save();

        $product = Product::create([
            'seller_id' => $seller->id, 'category_id' => $this->category->id,
            'brand_id' => (Brand::first() ?? Brand::create(['name' => 'No Brand']))->id,
            'name' => 'Ribbon', 'slug' => ['uz' => 'moq-uz', 'ru' => 'moq-ru', 'en' => 'moq-en'],
            'status' => 'approved', 'moq_pcs' => $moq,
        ]);
        $product->prices()->where('unit', 'pcs')->firstOrFail()->update(['price' => 1000]);

        return $product;
    }

    private function moderator(): User
    {
        $role = Role::create(['type' => 'admin', 'name' => 'Mod', 'slug' => 'mod-'.uniqid(), 'is_super_admin' => false]);
        $role->permissions()->attach(Permission::firstOrCreate(['slug' => 'catalog.manage'], ['name' => 'catalog.manage']));
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    // ---- N5-01

    public function test_product_page_quantity_starts_at_and_cannot_go_below_the_moq(): void
    {
        $this->product(10);
        app()->setLocale('en');

        Livewire::test(ProductShow::class, ['productSlug' => 'moq-en'])
            ->assertSet('qty', 10)
            ->call('decrementQty')
            ->assertSet('qty', 10)
            ->set('qty', 3)
            ->assertSet('qty', 10)
            ->call('incrementQty')
            ->assertSet('qty', 11)
            ->call('decrementQty')
            ->assertSet('qty', 10);
    }

    public function test_a_request_cannot_go_below_the_moq(): void
    {
        $product = $this->product(10);
        OfferSelectionService::ensure($product->id, 'pcs', 1);

        $component = Livewire::test(OfferRequestShow::class);

        foreach (range(1, 12) as $i) {
            $component->call('decrementLine', $product->seller_id, $product->id, 'pcs');
        }

        $this->assertSame(10, OfferSelectionService::currentQty($product->seller_id, $product->id, 'pcs'));

        $component->set('phone', '+998901119999');
        $this->travel(5)->seconds();
        $component->call('submit')->assertHasNoErrors();

        $this->assertSame(10, OfferRequest::sole()->items()->sole()->quantity);
    }

    public function test_a_line_added_before_the_moq_was_raised_is_lifted_at_submit(): void
    {
        $product = $this->product(1);
        OfferSelectionService::ensure($product->id, 'pcs', 2);
        $product->update(['moq_pcs' => 50]);

        $component = Livewire::test(OfferRequestShow::class)->set('phone', '+998901119999');
        $this->travel(5)->seconds();
        $component->call('submit')->assertHasNoErrors();

        $this->assertSame(50, OfferRequest::sole()->items()->sole()->quantity);
    }

    public function test_moq_for_a_larger_unit_rounds_up(): void
    {
        $product = $this->product(25);
        $product->prices()->create(['unit' => 'box', 'qty_in_pcs' => 10, 'price' => 9000, 'is_vitrin' => false]);

        $this->assertSame(3, OfferSelectionService::minQtyFor($product->id, 'box'));
    }

    // ---- N5-02

    public function test_upload_limit_is_the_smaller_php_setting_and_reaches_the_page(): void
    {
        $this->assertSame(
            min($this->iniBytes('upload_max_filesize'), $this->iniBytes('post_max_size')),
            UploadLimit::bytes(),
        );

        $this->get('/sellers/register')->assertOk()->assertSee('name="upload-limit"', false);
    }

    private function iniBytes(string $key): int
    {
        $value = (string) ini_get($key);
        $units = ['K' => 1024, 'M' => 1048576, 'G' => 1073741824];

        return (int) $value * ($units[strtoupper(substr($value, -1))] ?? 1);
    }

    // ---- N5-04

    public function test_moderator_cannot_read_the_free_flag(): void
    {
        $moderator = $this->moderator();

        Livewire::actingAs($moderator)->test(CategoriesIndex::class)
            ->assertDontSee(__('admin.categories.free_badge'));

        Livewire::actingAs($moderator)->test(CategoriesShow::class, ['category' => $this->category])
            ->assertSet('catIsFree', false);

        $this->assertFalse($this->category->fresh()->requires_subscription);
    }

    public function test_a_moderators_save_leaves_a_free_category_free(): void
    {
        Livewire::actingAs($this->moderator())
            ->test(CategoriesShow::class, ['category' => $this->category])
            ->call('saveCategoryDetails')
            ->assertHasNoErrors();

        $this->assertFalse($this->category->fresh()->requires_subscription);
    }

    // ---- N5-05

    public function test_the_lockout_message_replaces_the_old_error(): void
    {
        config(['ribbon.admin_2fa_required' => true]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());
        $service = app(TwoFactorService::class);
        $service->pendingFor($admin);
        $service->confirm($admin, app(Google2FA::class)->getCurrentOtp($admin->hasOne(UserTwoFactor::class)->first()->secret));

        $component = Livewire::actingAs($admin)->test(TwoFactorChallenge::class);

        foreach (range(1, 6) as $i) {
            $component->set('code', '000000')->call('verify');
        }

        $errors = $component->errors()->get('code');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString(explode(':seconds', __('auth.two_factor.throttled'))[0], $errors[0]);
    }

    // ---- N5-06, N5-12

    public function test_uzbek_dates_use_latin_months(): void
    {
        $this->get('/?lang=uz')->assertOk();

        $this->assertDoesNotMatchRegularExpression('/\p{Cyrillic}/u', Carbon::parse('2026-11-05')->translatedFormat('d M Y'));
    }

    public function test_the_app_runs_on_tashkent_time(): void
    {
        $this->assertSame('Asia/Tashkent', config('app.timezone'));
    }

    // ---- N5-10

    public function test_sanitizer_removes_page_takeover_markup(): void
    {
        $clean = HtmlSanitizer::clean('<base href="https://evil.example/"><p style="position:fixed;inset:0">x</p><svg><a href="/x">y</a></svg><math></math>');

        foreach (['<base', 'position:fixed', '<svg', '<math'] as $bad) {
            $this->assertStringNotContainsString($bad, $clean);
        }
    }

    public function test_sanitizer_keeps_the_editors_image_alignment_only(): void
    {
        $aligned = HtmlSanitizer::clean('<img src="/a.png" style="float: left; margin: 0 1rem 0.5rem 0;">');
        $this->assertStringContainsString('float: left', $aligned);

        $overlay = HtmlSanitizer::clean('<img src="/a.png" style="position:fixed;inset:0;width:100vw">');
        $this->assertStringNotContainsString('position', $overlay);
    }

    // ---- N5-11

    public function test_an_invalid_input_is_marked_for_screen_readers(): void
    {
        $html = view('components.input', ['error' => true, 'attributes' => new ComponentAttributeBag(['id' => 'req-phone'])])->render();

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="req-phone-error"', $html);
    }

    // ---- N5-14

    public function test_tampered_livewire_exceptions_are_not_reported_as_server_errors(): void
    {
        $handler = app(ExceptionHandler::class);
        $exception = new MethodNotFoundException('hack');

        $this->assertFalse($handler->shouldReport($exception));
        $this->assertSame(400, $handler->render(request(), $exception)->getStatusCode());
    }

    // ---- Owner's layout note

    public function test_admin_form_pages_are_centred(): void
    {
        foreach (['settings/show', 'offers/show', 'products/show', 'articles/form', 'banners/form', 'sellers/show', 'pages/form'] as $view) {
            $this->assertStringContainsString('mx-auto w-full max-w-4xl', file_get_contents(resource_path("views/livewire/admin/{$view}.blade.php")), $view);
        }
    }
}
