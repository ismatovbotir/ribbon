<?php

namespace Tests\Feature;

use App\Mcp\Servers\RibbonAdminServer;
use App\Mcp\Tools\ListChatLeadsTool;
use App\Mcp\Tools\ListOfferRequestsTool;
use App\Mcp\Tools\ModerateProductTool;
use App\Mcp\Tools\ModerateSellerTool;
use App\Mcp\Tools\OverviewTool;
use App\Mcp\Tools\RecordPaymentTool;
use App\Mcp\Tools\RevealBuyerContactTool;
use App\Mcp\Tools\TeachBotTool;
use App\Models\BotAnswer;
use App\Models\BotUnanswered;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\ContactMessage;
use App\Models\Country;
use App\Models\OfferRequest;
use App\Models\Product;
use App\Models\Region;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpAdminServerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function user(string $role = 'super-admin'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());

        return $user;
    }

    private function seller(): Seller
    {
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::create(['name' => $names]);
        $region = Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::create(['region_id' => $region->id, 'name' => $names]);

        return Seller::register(
            ['name' => 'Acme', 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id, 'city_id' => $city->id, 'vat_number' => '123456789', 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password', 'locale' => 'uz'],
        );
    }

    public function test_only_a_super_admin_can_use_the_tools(): void
    {
        // Local (SSH) mode has no logged-in user: it acts as MCP_ADMIN_EMAIL, which must be a Super Admin.
        RibbonAdminServer::tool(OverviewTool::class)->assertHasErrors(['MCP_ADMIN_EMAIL']);
        config(['ribbon.mcp_admin_email' => $this->user('moderator')->email]);
        RibbonAdminServer::tool(OverviewTool::class)->assertHasErrors();
        config(['ribbon.mcp_admin_email' => $this->user()->email]);
        RibbonAdminServer::tool(OverviewTool::class)->assertOk()->assertHasNoErrors()->assertSee('pending_review');

        RibbonAdminServer::actingAs($this->user('moderator'))->tool(OverviewTool::class)->assertHasErrors();
    }

    public function test_buyer_contacts_are_masked_until_one_is_revealed(): void
    {
        $admin = $this->user();
        $request = OfferRequest::create(['phone' => '+998901112233', 'email' => 'buyer@mail.uz', 'company_name' => 'Buyer LLC', 'status' => 'pending']);
        $lead = ContactMessage::create(['source' => 'chat', 'phone' => '+998935556677', 'message' => 'call me on whatsapp +998 93 555 66 77 or @buyer_tg', 'locale' => 'ru', 'status' => 'new']);

        RibbonAdminServer::actingAs($admin)->tool(ListOfferRequestsTool::class)
            ->assertSee(['+99890*****33', 'b***@mail.uz', 'Buyer LLC'])
            ->assertDontSee(['901112233', 'buyer@mail.uz']);

        RibbonAdminServer::actingAs($admin)->tool(ListChatLeadsTool::class)
            ->assertSee('+99893*****77')
            ->assertDontSee(['935556677', '555 66 77', '@buyer_tg']);

        RibbonAdminServer::actingAs($admin)->tool(RevealBuyerContactTool::class, ['type' => 'offer_request', 'id' => $request->id])
            ->assertSee(['+998901112233', 'buyer@mail.uz']);
        RibbonAdminServer::actingAs($admin)->tool(RevealBuyerContactTool::class, ['type' => 'chat_lead', 'id' => $lead->id])
            ->assertSee('+998935556677');
    }

    public function test_seller_moderation_is_done_as_the_super_admin(): void
    {
        $admin = $this->user();
        $seller = $this->seller();

        RibbonAdminServer::actingAs($admin)->tool(ModerateSellerTool::class, ['seller_id' => $seller->id, 'decision' => 'suspend', 'reason' => 'x'])
            ->assertHasErrors(); // only approved sellers can be suspended
        RibbonAdminServer::actingAs($admin)->tool(ModerateSellerTool::class, ['seller_id' => $seller->id, 'decision' => 'reject'])
            ->assertHasErrors(); // reason required

        RibbonAdminServer::actingAs($admin)->tool(ModerateSellerTool::class, ['seller_id' => $seller->id, 'decision' => 'approve'])
            ->assertOk()->assertSee('pending → approved');

        $seller->refresh();
        $this->assertSame('approved', $seller->status);
        $this->assertSame($admin->id, $seller->approved_by);
    }

    public function test_a_product_without_complete_prices_cannot_be_approved(): void
    {
        $seller = $this->seller();
        $names = ['uz' => 'C', 'ru' => 'C', 'en' => 'C'];
        $category = Category::create(['name' => $names, 'slug' => ['uz' => 'c-uz', 'ru' => 'c-ru', 'en' => 'c-en'], 'is_active' => true, 'requires_subscription' => false]);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'brand_id' => Brand::create(['name' => 'B'])->id, 'name' => 'P', 'slug' => ['uz' => 'p-uz', 'ru' => 'p-ru', 'en' => 'p-en'], 'status' => 'pending']);

        RibbonAdminServer::actingAs($this->user())->tool(ModerateProductTool::class, ['product_id' => $product->id, 'decision' => 'approve'])
            ->assertHasErrors();
        $this->assertSame('pending', $product->fresh()->status);

        RibbonAdminServer::actingAs($this->user())->tool(ModerateProductTool::class, ['product_id' => $product->id, 'decision' => 'reject', 'reason' => 'Wrong category'])
            ->assertOk();
        $this->assertSame('rejected', $product->fresh()->status);
    }

    public function test_a_payment_extends_the_subscription(): void
    {
        $seller = $this->seller();

        RibbonAdminServer::actingAs($this->user())->tool(RecordPaymentTool::class, ['seller_id' => $seller->id, 'amount_uzs' => 0, 'months' => 1])
            ->assertHasErrors(); // 0 only with free=true

        RibbonAdminServer::actingAs($this->user())->tool(RecordPaymentTool::class, ['seller_id' => $seller->id, 'amount_uzs' => 750000, 'months' => 2, 'note' => 'transfer 15'])
            ->assertOk();

        $this->assertSame(today()->addMonths(2)->toDateString(), $seller->fresh()->subscription_paid_until->toDateString());
    }

    public function test_teaching_the_bot_adds_keywords_and_resolves_the_question(): void
    {
        $answer = BotAnswer::create(['name' => 'Warranty', 'reply' => array_fill_keys(config('ribbon.locales'), 'x'), 'keywords' => ['ru' => ['гарантия']]]);
        $question = BotUnanswered::create(['text' => 'срок гарантии на принтер', 'fingerprint' => sha1('q'), 'locale' => 'ru', 'channel' => 'web', 'times' => 2, 'last_asked_at' => now()]);

        RibbonAdminServer::actingAs($this->user())->tool(TeachBotTool::class, [
            'answer_id' => $answer->id, 'language' => 'ru', 'keywords' => ['срок гарантии', 'Гарантия'], 'question_id' => $question->id,
        ])->assertOk();

        $this->assertSame(['гарантия', 'срок гарантии'], $answer->fresh()->keywords['ru']);
        $this->assertSame($answer->id, $question->fresh()->bot_answer_id);
        $this->assertNotNull($question->fresh()->resolved_at);
    }

    public function test_the_server_lists_its_tools(): void
    {
        RibbonAdminServer::actingAs($this->user())->tools()->assertRegistered([OverviewTool::class, ModerateSellerTool::class, RecordPaymentTool::class, TeachBotTool::class]);
    }
}
