<?php

namespace Tests\Feature;

use App\Models\BotAnswer;
use App\Models\BotUnanswered;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\CategoryParameterOption;
use App\Models\City;
use App\Models\Country;
use App\Models\Product;
use App\Models\ProductParameterValue;
use App\Models\Region;
use App\Models\Seller;
use App\Services\Bot\BotEngine;
use App\Services\Bot\BotText;
use Database\Seeders\BotAnswerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotEngineTest extends TestCase
{
    use RefreshDatabase;

    private function engine(): BotEngine
    {
        return new BotEngine;
    }

    private function answer(string $name, array $keywords, array $buttons = [], bool $start = false): BotAnswer
    {
        $answer = BotAnswer::create([
            'name' => $name,
            'reply' => array_fill_keys(config('ribbon.locales'), $name.' reply'),
            'keywords' => $keywords,
            'buttons' => $buttons,
        ]);

        if ($start) {
            $answer->makeStart();
        }

        return $answer;
    }

    public function test_keywords_match_whatever_the_word_ending_and_in_any_language(): void
    {
        $prices = $this->answer('Prices', ['ru' => ['цена'], 'uz' => ['narx']]);

        $this->assertSame($prices->id, $this->engine()->ask('Какие у вас цены?', 'ru')->answerId);
        $this->assertSame($prices->id, $this->engine()->ask('narxi qancha', 'uz')->answerId);
        $this->assertSame($prices->id, $this->engine()->ask('narxi qancha', 'en')->answerId);
    }

    public function test_the_longest_keyword_wins_and_short_keywords_need_an_exact_word(): void
    {
        $this->answer('Ribbon', ['en' => ['ribbon']]);
        $price = $this->answer('Ribbon price', ['en' => ['ribbon price']]);
        $hi = $this->answer('Hello', ['en' => ['hi']]);

        $this->assertSame($price->id, $this->engine()->ask('what is the ribbon price?', 'en')->answerId);
        $this->assertSame($hi->id, $this->engine()->ask('Hi there', 'en')->answerId);
        $this->assertNotSame($hi->id, $this->engine()->ask('hitachi printer', 'en')->answerId);
    }

    public function test_uzbek_apostrophe_variants_are_the_same_letter(): void
    {
        $payment = $this->answer('Payment', ['uz' => ["to'lov"]]);

        $this->assertSame($payment->id, $this->engine()->ask('to‘lov qanday', 'uz')->answerId);
        $this->assertSame($payment->id, $this->engine()->ask('toʻlov', 'uz')->answerId);
    }

    public function test_an_unanswered_question_is_saved_masked_and_counted(): void
    {
        $reply = $this->engine()->ask('Позвоните мне +998 90 123-45-67 или a.b@mail.uz', 'ru');
        $this->engine()->ask('Позвоните мне +998 90 123-45-67 или a.b@mail.uz', 'ru');

        $this->assertSame('fallback', $reply->kind);
        $row = BotUnanswered::sole();
        $this->assertSame(2, $row->times);
        $this->assertStringContainsString('[phone]', $row->text);
        $this->assertStringContainsString('[email]', $row->text);
        $this->assertStringNotContainsString('123', $row->text);
        $this->assertStringNotContainsString('mail.uz', $row->text);
    }

    public function test_test_mode_saves_nothing_and_counts_nothing(): void
    {
        $answer = $this->answer('Prices', ['en' => ['price']]);

        $this->engine()->ask('price?', 'en', 'admin', learn: false);
        $this->engine()->ask('something unknown', 'en', 'admin', learn: false);

        $this->assertSame(0, $answer->fresh()->hits);
        $this->assertSame(0, BotUnanswered::count());

        $this->engine()->ask('price?', 'en');
        $this->assertSame(1, $answer->fresh()->hits);
    }

    public function test_every_reply_offers_the_menu_and_a_person_and_drops_broken_or_unsafe_buttons(): void
    {
        $label = fn (string $t) => array_fill_keys(config('ribbon.locales'), $t);
        $answer = $this->answer('Links', ['en' => ['links']], [
            ['action' => 'answer', 'target' => '999', 'label' => $label('Gone')],
            ['action' => 'url', 'target' => 'javascript:alert(1)', 'label' => $label('Bad')],
            ['action' => 'url', 'target' => '//evil.example', 'label' => $label('Bad 2')],
            ['action' => 'url', 'target' => '/contact', 'label' => $label('Contact')],
        ]);

        $buttons = collect($this->engine()->ask('links', 'en')->buttons);

        $this->assertSame(['Contact', __('storefront.bot.menu', [], 'en'), __('storefront.bot.handoff', [], 'en')], $buttons->pluck('label')->all());
        $this->assertSame(url('/contact'), $buttons->first()['url']);
        $this->assertSame($answer->id, $this->engine()->ask('links', 'en')->answerId);
    }

    public function test_start_uses_the_taught_start_menu_or_a_safe_default(): void
    {
        $default = $this->engine()->start('ru');
        $this->assertSame(__('storefront.bot.start_default', [], 'ru'), $default->text);
        $this->assertSame(['finder', 'handoff'], array_column($default->buttons, 'action'));

        $start = $this->answer('Start', [], start: true);
        $this->assertSame($start->id, $this->engine()->start('ru')->answerId);
        $this->assertNotContains('start', array_column($this->engine()->start('ru')->buttons, 'action'));
    }

    public function test_lead_and_handoff_presses_ask_for_the_form(): void
    {
        $this->assertSame('lead', $this->engine()->press('lead', '', 'en')->form);
        $this->assertSame('handoff', $this->engine()->press('handoff', '', 'en')->form);
        $this->assertSame('start', $this->engine()->press('nonsense', 'x', 'en')->matchedBy);
    }

    private function catalog(): array
    {
        Brand::firstOrCreate(['name' => 'No Brand']);
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::create(['name' => $names]);
        $region = Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::create(['region_id' => $region->id, 'name' => $names]);
        $seller = Seller::register(
            ['name' => 'S', 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id, 'city_id' => $city->id, 'vat_number' => '300123456', 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => 'bot-owner@example.test', 'password' => 'password', 'locale' => 'uz'],
        );
        $seller->forceFill(['status' => 'approved'])->save();

        $category = Category::create([
            'name' => ['uz' => 'Termotransfer lentalar', 'ru' => 'Термотрансферные риббоны', 'en' => 'Thermal transfer ribbons'],
            'slug' => ['uz' => 'lentalar', 'ru' => 'ribbony', 'en' => 'ribbons'],
            'is_active' => true,
            'requires_subscription' => false,
        ]);
        $type = CategoryParameter::create(['category_id' => $category->id, 'name' => ['uz' => 'Turi', 'ru' => 'Тип', 'en' => 'Type'], 'type' => 'select_single', 'is_required' => false, 'is_filterable' => true, 'sort_order' => 1]);
        $wax = CategoryParameterOption::create(['category_parameter_id' => $type->id, 'value' => ['uz' => 'Wax', 'ru' => 'Wax', 'en' => 'Wax'], 'sort_order' => 1]);
        $resin = CategoryParameterOption::create(['category_parameter_id' => $type->id, 'value' => ['uz' => 'Resin', 'ru' => 'Resin', 'en' => 'Resin'], 'sort_order' => 2]);
        $unused = CategoryParameterOption::create(['category_parameter_id' => $type->id, 'value' => ['uz' => 'Mix', 'ru' => 'Mix', 'en' => 'Mix'], 'sort_order' => 3]);

        foreach ([$wax, $wax, $wax, $resin] as $i => $option) {
            $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'brand_id' => Brand::first()->id, 'name' => "P$i", 'slug' => ['uz' => "p$i", 'ru' => "p$i", 'en' => "p$i"], 'status' => 'approved']);
            $value = ProductParameterValue::create(['product_id' => $product->id, 'category_parameter_id' => $type->id]);
            $value->options()->create(['category_parameter_option_id' => $option->id]);
        }

        return [$category, $type, $wax, $resin, $unused];
    }

    public function test_the_product_finder_offers_only_options_with_products_and_ends_on_a_filtered_link(): void
    {
        [$category, $type, $wax, $resin] = $this->catalog();
        $engine = $this->engine();

        $step = $engine->press('finder', 'c'.$category->id, 'en');
        $options = collect($step->buttons)->where('action', 'finder')->where('target', '!=', '')->pluck('label')->all();
        $this->assertSame(['Wax · 3', 'Resin · 1'], $options);

        $done = $engine->press('finder', 'c'.$category->id.'.'.$type->id.'-'.$wax->id, 'en');
        $link = collect($done->buttons)->firstWhere('url', '!=', null);
        $this->assertStringContainsString('/catalog/ribbons?', $link['url']);
        $this->assertStringContainsString(urlencode('f['.$type->id.'][0]').'='.$wax->id, $link['url']);
    }

    public function test_a_category_named_in_text_starts_the_finder_and_tampered_targets_restart_it(): void
    {
        [$category] = $this->catalog();

        $reply = $this->engine()->ask('нужны риббоны', 'ru');
        $this->assertSame('finder', $reply->kind);
        $this->assertStringStartsWith('category:', (string) $reply->matchedBy);

        $restart = $this->engine()->press('finder', 'c'.$category->id.'.99999-1;DROP', 'ru');
        $this->assertSame(__('storefront.bot.finder_pick_category', [], 'ru'), $restart->text);
    }

    public function test_a_long_question_that_mentions_a_category_is_saved_and_offers_the_category(): void
    {
        [$category] = $this->catalog();

        $reply = $this->engine()->ask('есть ли гарантия на эти риббоны и как долго?', 'ru');

        $this->assertSame('fallback', $reply->kind);
        $this->assertSame('c'.$category->id, $reply->buttons[0]['target']);
        $this->assertSame(1, BotUnanswered::count());
    }

    public function test_starter_answers_seed_once_and_never_overwrite_staff_edits(): void
    {
        $this->seed(BotAnswerSeeder::class);
        BotAnswer::where('key', 'prices')->update(['name' => 'Edited by staff']);
        $this->seed(BotAnswerSeeder::class);

        $this->assertSame('Edited by staff', BotAnswer::where('key', 'prices')->value('name'));
        $this->assertSame(1, BotAnswer::where('is_start', true)->count());
        $this->assertSame(BotAnswer::where('key', 'prices')->value('id'), $this->engine()->ask('сколько стоит лента?', 'ru')->answerId);
    }

    public function test_starter_answers_route_common_questions_to_the_right_topic(): void
    {
        $this->seed(BotAnswerSeeder::class);
        $id = fn (string $key) => BotAnswer::where('key', $key)->value('id');

        $this->assertSame($id('delivery'), $this->engine()->ask('как работает доставка?', 'ru')->answerId);
        $this->assertSame($id('how-it-works'), $this->engine()->ask('как работает ribbon?', 'ru')->answerId);
        $this->assertSame($id('prices'), $this->engine()->ask('почём ленты', 'ru')->answerId);
        $this->assertSame($id('payment'), $this->engine()->ask("to'lov qanday", 'uz')->answerId);
        $this->assertSame($id('delivery'), $this->engine()->ask('how does delivery work', 'en')->answerId);
        $this->assertSame($id('delivery'), $this->engine()->ask('сколько стоит доставка', 'ru')->answerId);
        $this->assertSame($id('delivery'), $this->engine()->ask('how much does delivery cost', 'en')->answerId);
        $this->assertSame($id('delivery'), $this->engine()->ask('dostavka narxi qancha', 'uz')->answerId);
        $this->assertSame($id('prices'), $this->engine()->ask('сколько стоит лента', 'ru')->answerId);
    }

    public function test_masking_keeps_ordinary_numbers_like_widths(): void
    {
        $this->assertSame('ribbon 110 mm 300 m [phone]', BotText::mask('ribbon 110 mm 300 m +998901234567'));
    }

    public function test_masking_also_hides_underscore_and_slash_phones_and_telegram_usernames(): void
    {
        $this->assertSame('call [phone] or [phone], or write [telegram] / [email]', BotText::mask('call 90_123_45_67 or 90/123/45/67, or write @my_name / me@mail.uz'));
    }

    public function test_links_must_be_a_site_path_or_a_real_https_address(): void
    {
        $this->assertNotNull(BotEngine::safeUrl('https://t.me/ribbon_bot'));
        $this->assertNotNull(BotEngine::safeUrl('/contact'));

        foreach (['https://x', 'https://', '//evil.com', 'javascript:alert(1)', 'http://ribbon.uz', 'https://a b.uz'] as $bad) {
            $this->assertNull(BotEngine::safeUrl($bad), $bad);
        }

        config(['app.url' => 'https://ribbon.uz']);
        $this->assertFalse(BotEngine::isExternal('https://ribbon.uz/catalog'));
        $this->assertFalse(BotEngine::isExternal('/catalog'));
        $this->assertTrue(BotEngine::isExternal('https://evil.example/login'));
    }

    public function test_new_questions_stop_being_collected_at_the_cap_but_known_ones_still_count(): void
    {
        $now = now();
        foreach (array_chunk(range(1, BotUnanswered::MAX_OPEN), 500) as $chunk) {
            BotUnanswered::insert(array_map(fn ($i) => ['text' => "q{$i}", 'fingerprint' => sha1("q{$i}"), 'locale' => 'en', 'channel' => 'web', 'times' => 1, 'last_asked_at' => $now, 'created_at' => $now, 'updated_at' => $now], $chunk));
        }
        $known = BotUnanswered::create(['text' => 'known words', 'fingerprint' => sha1('en|known words'), 'locale' => 'en', 'channel' => 'web', 'times' => 1, 'last_asked_at' => $now, 'resolved_at' => $now]);

        $this->engine()->ask('brand new junk text', 'en');
        $this->engine()->ask('known words', 'en');

        $this->assertSame(BotUnanswered::MAX_OPEN + 1, BotUnanswered::count());
        $this->assertSame(2, $known->fresh()->times);
        $this->assertNull($known->fresh()->resolved_at);
    }

    public function test_old_one_off_and_long_handled_questions_are_pruned(): void
    {
        $row = fn (string $text, int $times, $asked, $resolved = null) => BotUnanswered::create(['text' => $text, 'fingerprint' => sha1($text), 'locale' => 'en', 'channel' => 'web', 'times' => $times, 'last_asked_at' => $asked, 'resolved_at' => $resolved]);

        $row('old junk', 1, now()->subDays(31));
        $row('old but repeated', 4, now()->subDays(31));
        $row('fresh', 1, now()->subDays(2));
        $row('handled long ago', 3, now()->subDays(100), now()->subDays(91));
        $row('handled lately', 3, now()->subDays(100), now()->subDays(10));

        $this->artisan('model:prune', ['--model' => [BotUnanswered::class]])->assertSuccessful();

        $this->assertSame(['fresh', 'handled lately', 'old but repeated'], BotUnanswered::orderBy('text')->pluck('text')->all());
    }
}
