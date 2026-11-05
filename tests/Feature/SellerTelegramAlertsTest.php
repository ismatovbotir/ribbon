<?php

namespace Tests\Feature;

use App\Jobs\NotifySellersOfOfferRequest;
use App\Jobs\NotifyTelegramOfSubscriptionExpiry;
use App\Livewire\Sellers\Profile\Index as Profile;
use App\Livewire\Storefront\OfferRequest\Show as OfferRequestShow;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\OfferRequest;
use App\Models\OfferRequestItem;
use App\Models\OfferRequestOutcome;
use App\Models\Product;
use App\Models\Region;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\TelegramLink;
use App\Models\TelegramLinkToken;
use App\Models\TelegramMessage;
use App\Models\User;
use App\Services\OfferSelectionService;
use App\Services\SellerTelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SellerTelegramAlertsTest extends TestCase
{
    use RefreshDatabase;

    private Seller $paid;

    private Seller $free;

    private OfferRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);

        Setting::current()->update([
            'telegram_bot_token' => 'bot-token',
            'telegram_bot_username' => 'ribbon_bot',
            'telegram_webhook_secret' => 'the-secret',
        ]);

        $this->paid = $this->makeSeller('paid');
        $this->paid->forceFill(['subscription_paid_until' => today()->addMonth()])->save();
        $this->free = $this->makeSeller('free');

        $this->request = OfferRequest::create([
            'phone' => '+998901112233',
            'company_name' => 'Buyer <b>LLC</b>',
            'buyer_type' => 'retail',
            'status' => 'pending',
        ]);
        $this->addItem($this->request, $this->paid, 'PaidItem');
        $this->addItem($this->request, $this->free, 'FreeItem');
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

    private function link(User $user, string $chatId): void
    {
        TelegramLink::create(['user_id' => $user->id, 'chat_id' => $chatId]);
    }

    private function webhook(array $update)
    {
        return $this->postJson('/telegram/webhook', $update, ['X-Telegram-Bot-Api-Secret-Token' => 'the-secret']);
    }

    private function startUpdate(string $token, int $chatId = 555, string $type = 'private'): array
    {
        return ['message' => [
            'message_id' => 1,
            'text' => "/start {$token}",
            'chat' => ['id' => $chatId, 'type' => $type],
            'from' => ['id' => $chatId, 'username' => 'sales'],
        ]];
    }

    private function press(int $fromId, string $data, ?int $chatId = null): array
    {
        return ['callback_query' => [
            'id' => 'cb1',
            'from' => ['id' => $fromId],
            'data' => $data,
            'message' => ['message_id' => 77, 'chat' => ['id' => $chatId ?? $fromId]],
        ]];
    }

    private function tokenFrom(string $url): string
    {
        return substr($url, strpos($url, 'start=') + 6);
    }

    /** @return array<int, HttpRequest> */
    private function sentMessages(string $chatId): array
    {
        return collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (HttpRequest $r) => str_ends_with($r->url(), '/sendMessage') && (string) $r['chat_id'] === $chatId)
            ->values()
            ->all();
    }

    // ---- Linking ----

    public function test_a_link_connects_the_right_user_once(): void
    {
        $service = app(SellerTelegramService::class);
        $owner = $this->owner($this->paid);
        $token = $this->tokenFrom($service->linkUrlFor($owner));

        $this->webhook($this->startUpdate($token))->assertOk();

        $this->assertSame('555', $owner->fresh()->telegramLink->chat_id);
        $this->assertSame(0, TelegramLinkToken::count());

        // Single use: a second chat can't reuse it.
        $this->webhook($this->startUpdate($token, 666))->assertOk();
        $this->assertSame(1, TelegramLink::count());
        $this->assertSame('555', $owner->fresh()->telegramLink->chat_id);
    }

    public function test_the_token_is_hashed_and_never_saved_to_the_admin_inbox(): void
    {
        $url = app(SellerTelegramService::class)->linkUrlFor($this->owner($this->paid));
        $token = $this->tokenFrom($url);

        $this->assertStringStartsWith('https://t.me/ribbon_bot?start=', $url);
        $this->assertNotSame($token, TelegramLinkToken::sole()->token_hash);

        $this->webhook($this->startUpdate($token))->assertOk();

        $this->assertSame(0, TelegramMessage::count());
    }

    public function test_an_expired_token_does_not_link(): void
    {
        $token = $this->tokenFrom(app(SellerTelegramService::class)->linkUrlFor($this->owner($this->paid)));

        $this->travel(16)->minutes();
        $this->webhook($this->startUpdate($token))->assertOk();

        $this->assertSame(0, TelegramLink::count());
    }

    public function test_a_group_chat_cannot_link(): void
    {
        $token = $this->tokenFrom(app(SellerTelegramService::class)->linkUrlFor($this->owner($this->paid)));

        $this->webhook($this->startUpdate($token, -100, 'group'))->assertOk();

        $this->assertSame(0, TelegramLink::count());
    }

    public function test_a_new_link_replaces_the_previous_unused_one(): void
    {
        $service = app(SellerTelegramService::class);
        $owner = $this->owner($this->paid);
        $old = $this->tokenFrom($service->linkUrlFor($owner));
        $service->linkUrlFor($owner);

        $this->webhook($this->startUpdate($old))->assertOk();

        $this->assertSame(0, TelegramLink::count());
    }

    public function test_webhook_still_requires_the_secret(): void
    {
        $this->postJson('/telegram/webhook', $this->press(555, 'ro:1:won'))->assertForbidden();
    }

    public function test_plain_messages_still_reach_the_admin_inbox(): void
    {
        $this->webhook(['message' => ['message_id' => 2, 'text' => 'hello', 'chat' => ['id' => 9, 'type' => 'private'], 'from' => ['id' => 9]]])->assertOk();

        $this->assertSame(1, TelegramMessage::count());
    }

    // ---- Profile ----

    public function test_employee_can_connect_and_disconnect_their_own_telegram(): void
    {
        $employee = $this->paid->addEmployee(['name' => 'E', 'email' => 'e@example.test', 'password' => 'password', 'locale' => 'en']);

        $component = Livewire::actingAs($employee)->test(Profile::class)
            ->call('connectTelegram')
            ->assertSee(__('sellers.telegram.open'));
        $this->assertSame($employee->id, TelegramLinkToken::sole()->user_id);

        $this->link($employee, '901');
        $this->link($this->owner($this->paid), '902');

        $component->call('disconnectTelegram');

        $this->assertNull($employee->fresh()->telegramLink);
        $this->assertNotNull($this->owner($this->paid)->fresh()->telegramLink);
    }

    public function test_profile_explains_the_tier(): void
    {
        Livewire::actingAs($this->owner($this->paid))->test(Profile::class)->assertSee(__('sellers.telegram.tier_full'));
        Livewire::actingAs($this->owner($this->free))->test(Profile::class)->assertSee(__('sellers.telegram.tier_free'));
    }

    public function test_profile_says_so_when_no_bot_is_connected(): void
    {
        Setting::current()->update(['telegram_bot_username' => null]);

        Livewire::actingAs($this->owner($this->paid))->test(Profile::class)
            ->assertSee(__('sellers.telegram.unavailable'))
            ->assertDontSee(__('sellers.telegram.connect'));
    }

    // ---- Alerts ----

    public function test_paid_seller_gets_full_details_only_about_their_own_items(): void
    {
        $this->link($this->owner($this->paid), '100');

        NotifySellersOfOfferRequest::dispatchSync($this->request);

        $message = $this->sentMessages('100')[0];
        $this->assertStringContainsString('+998901112233', $message['text']);
        $this->assertStringContainsString('PaidItem', $message['text']);
        $this->assertStringNotContainsString('FreeItem', $message['text']);
        $this->assertStringNotContainsString('Seller free', $message['text']);
        $this->assertStringContainsString('Buyer &lt;b&gt;LLC&lt;/b&gt;', $message['text']);
        $this->assertCount(4, collect($message['reply_markup']['inline_keyboard'])->flatten(1));
    }

    public function test_seller_in_grace_still_gets_full_details(): void
    {
        $this->paid->forceFill(['subscription_paid_until' => today()->subDay()])->save();
        $this->link($this->owner($this->paid), '100');

        NotifySellersOfOfferRequest::dispatchSync($this->request);

        $this->assertStringContainsString('+998901112233', $this->sentMessages('100')[0]['text']);
    }

    public function test_free_and_lapsed_sellers_get_a_notification_with_no_buyer_details(): void
    {
        $this->link($this->owner($this->free), '200');
        $this->paid->forceFill(['subscription_paid_until' => today()->subYear()])->save();
        $this->link($this->owner($this->paid), '100');

        NotifySellersOfOfferRequest::dispatchSync($this->request);

        foreach (['200', '100'] as $chat) {
            $message = $this->sentMessages($chat)[0];
            $this->assertStringContainsString(__('sellers.telegram.alert_open_panel', [], 'en'), $message['text']);
            $this->assertStringContainsString(route('seller.requests'), $message['text']);
            foreach (['+998901112233', 'Buyer', 'PaidItem', 'FreeItem'] as $hidden) {
                $this->assertStringNotContainsString($hidden, $message['text']);
            }
            $this->assertArrayNotHasKey('reply_markup', $message->data());
        }
    }

    public function test_each_linked_person_is_alerted_in_their_own_language(): void
    {
        $owner = $this->owner($this->paid);
        $employee = $this->paid->addEmployee(['name' => 'E', 'email' => 'e@example.test', 'password' => 'password', 'locale' => 'ru']);
        $this->link($owner, '100');
        $this->link($employee, '101');

        NotifySellersOfOfferRequest::dispatchSync($this->request);

        $this->assertStringContainsString(__('sellers.telegram.alert_title', [], 'en'), $this->sentMessages('100')[0]['text']);
        $this->assertStringContainsString(__('sellers.telegram.alert_title', [], 'ru'), $this->sentMessages('101')[0]['text']);
    }

    public function test_suspended_sellers_and_unlinked_users_get_nothing(): void
    {
        $this->paid->forceFill(['status' => 'suspended'])->save();
        $this->link($this->owner($this->paid), '100');

        NotifySellersOfOfferRequest::dispatchSync($this->request);

        $this->assertSame([], $this->sentMessages('100'));
        Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/sendMessage'));
    }

    public function test_a_failed_delivery_does_not_stop_the_other_sellers(): void
    {
        Http::fake(['api.telegram.org/*' => Http::sequence()
            ->push(['ok' => false], 403)
            ->push(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->link($this->owner($this->paid), '100');
        $this->link($this->owner($this->free), '200');

        NotifySellersOfOfferRequest::dispatchSync($this->request);

        Http::assertSentCount(2);
    }

    public function test_submitting_a_request_alerts_sellers_after_the_response(): void
    {
        Bus::fake();
        OfferSelectionService::add($this->request->items()->where('seller_id', $this->paid->id)->first()->product, 'pcs', 1);

        $component = Livewire::test(OfferRequestShow::class)->set('phone', '+998901119999');
        $this->travel(5)->seconds();
        $component->call('submit')->assertHasNoErrors();

        Bus::assertDispatchedAfterResponse(NotifySellersOfOfferRequest::class);
    }

    // ---- Buttons ----

    public function test_a_button_press_records_the_outcome_as_the_linked_user(): void
    {
        $owner = $this->owner($this->paid);
        $this->link($owner, '100');

        $this->webhook($this->press(100, "ro:{$this->request->id}:won"))->assertOk();

        $outcome = OfferRequestOutcome::sole();
        $this->assertSame('won', $outcome->outcome);
        $this->assertSame($this->paid->id, $outcome->seller_id);
        $this->assertSame($owner->id, $outcome->marked_by);

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/answerCallbackQuery') && $r['callback_query_id'] === 'cb1');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/editMessageReplyMarkup')
            && str_contains(json_encode($r['reply_markup'], JSON_UNESCAPED_UNICODE), '✓'));
    }

    public function test_a_press_on_another_sellers_request_is_refused(): void
    {
        $private = OfferRequest::create(['phone' => '+998907777777', 'status' => 'pending']);
        $this->addItem($private, $this->free, 'FreeOnly');
        $this->link($this->owner($this->paid), '100');

        $this->webhook($this->press(100, "ro:{$private->id}:won"))->assertOk();

        $this->assertSame(0, OfferRequestOutcome::count());
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/answerCallbackQuery')
            && $r['text'] === __('sellers.telegram.outcome_refused'));
    }

    public function test_presses_from_unlinked_chats_or_with_bad_data_are_refused(): void
    {
        $this->link($this->owner($this->paid), '100');
        $id = $this->request->id;

        $this->webhook($this->press(999, "ro:{$id}:won"))->assertOk();
        $this->webhook($this->press(100, "ro:{$id}:hacked"))->assertOk();
        $this->webhook($this->press(100, 'garbage'))->assertOk();
        $this->webhook($this->press(100, "ro:{$id}:won", -100))->assertOk(); // from a linked user, in a group

        $this->assertSame(0, OfferRequestOutcome::count());
    }

    public function test_suspended_or_removed_users_cannot_press(): void
    {
        $owner = $this->owner($this->paid);
        $this->link($owner, '100');
        $this->paid->forceFill(['status' => 'suspended'])->save();

        $this->webhook($this->press(100, "ro:{$this->request->id}:won"))->assertOk();
        $this->assertSame(0, OfferRequestOutcome::count());

        $this->paid->forceFill(['status' => 'approved'])->save();
        $owner->sellers()->detach();

        $this->webhook($this->press(100, "ro:{$this->request->id}:won"))->assertOk();
        $this->assertSame(0, OfferRequestOutcome::count());
    }

    // ---- Expiry reminders ----

    public function test_expiry_reminders_reach_linked_sellers_in_their_language(): void
    {
        $employee = $this->paid->addEmployee(['name' => 'E', 'email' => 'e@example.test', 'password' => 'password', 'locale' => 'ru']);
        $this->link($employee, '101');

        $sent = NotifyTelegramOfSubscriptionExpiry::dispatchSync($this->paid, 'expiring', today()->addDays(3));

        $this->assertStringContainsString(
            'Оплачено до',
            $this->sentMessages('101')[0]['text'],
        );
        $this->assertNotFalse($sent);
    }
}
