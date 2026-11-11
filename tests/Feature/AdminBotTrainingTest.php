<?php

namespace Tests\Feature;

use App\Livewire\Admin\Bot\Form;
use App\Livewire\Admin\Bot\Index;
use App\Livewire\Admin\Bot\TestChat;
use App\Livewire\Admin\Bot\Unanswered;
use App\Models\BotAnswer;
use App\Models\BotUnanswered;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminBotTrainingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'super-admin'): User
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());

        return $user;
    }

    private function adminWithout(): User
    {
        $this->seed(PermissionSeeder::class);
        $role = Role::create(['type' => 'admin', 'name' => 'Pages only', 'slug' => 'pages-only', 'is_super_admin' => false]);
        $role->permissions()->attach(Permission::where('slug', 'pages.manage')->firstOrFail());
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function text(string $value): array
    {
        return array_fill_keys(config('ribbon.locales'), $value);
    }

    public function test_super_admin_and_moderator_can_open_every_bot_page(): void
    {
        foreach (['super-admin', 'moderator'] as $role) {
            $user = $this->admin($role);

            foreach (['/admin/bot', '/admin/bot/unanswered', '/admin/bot/test', '/admin/bot/guide', '/admin/bot/create'] as $url) {
                $this->actingAs($user)->get($url)->assertOk();
            }
        }
    }

    public function test_the_instructions_render_every_section_in_each_language_with_working_anchors(): void
    {
        $user = $this->admin('moderator');

        foreach (config('ribbon.locales') as $locale) {
            $sections = trans('admin.bot.guide', [], $locale);
            $this->assertIsArray($sections);
            $this->assertCount(10, $sections, $locale);

            $response = $this->actingAs($user)->get('/admin/bot/guide?lang='.$locale)->assertOk();

            foreach ($sections as $section) {
                $response->assertSee('id="'.$section['id'].'"', false)->assertSee($section['title']);
            }
        }

        // The editor links straight to the keyword and button sections.
        $this->actingAs($user)->get('/admin/bot/create')->assertSee('/admin/bot/guide#keywords', false)->assertSee('/admin/bot/guide#buttons', false);
    }

    public function test_staff_without_the_permission_and_guests_are_kept_out(): void
    {
        $this->get('/admin/bot')->assertRedirect();

        $user = $this->adminWithout();
        $this->actingAs($user)->get('/admin/bot')->assertForbidden();
        $this->actingAs($user)->get('/admin/bot/test')->assertForbidden();
        $this->actingAs($user)->get('/admin/bot/guide')->assertForbidden();

        Livewire::actingAs($user)->test(Index::class)->assertForbidden();
    }

    public function test_an_answer_saves_with_parsed_keywords_buttons_and_a_single_start(): void
    {
        $old = BotAnswer::create(['name' => 'Old start', 'reply' => $this->text('x')]);
        $old->makeStart();

        Livewire::actingAs($this->admin('moderator'))->test(Form::class)
            ->set('name', 'Prices')
            ->set('reply', $this->text('Prices are shown per unit.'))
            ->set('keywords.ru', "цена, стоимость\nсколько стоит\nЦена")
            ->call('addButton')
            ->set('buttons.0.action', 'url')
            ->set('buttons.0.target', '/contact')
            ->set('buttons.0.label', $this->text('Contact'))
            ->set('isStart', true)
            ->call('save')
            ->assertHasNoErrors();

        $answer = BotAnswer::where('name', 'Prices')->sole();
        $this->assertSame(['цена', 'стоимость', 'сколько стоит'], $answer->keywords['ru']);
        $this->assertSame([['action' => 'url', 'target' => '/contact', 'label' => $this->text('Contact')]], $answer->buttons);
        $this->assertTrue($answer->is_start);
        $this->assertFalse($old->fresh()->is_start);
    }

    public function test_missing_translations_and_unsafe_or_missing_targets_are_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(Form::class)
            ->set('name', 'X')
            ->set('reply', ['uz' => 'a', 'ru' => '', 'en' => 'c'])
            ->call('addButton')
            ->set('buttons.0.action', 'url')
            ->set('buttons.0.target', 'javascript:alert(1)')
            ->set('buttons.0.label', $this->text('Go'))
            ->call('addButton')
            ->set('buttons.1.action', 'answer')
            ->set('buttons.1.target', '')
            ->set('buttons.1.label', $this->text('Next'))
            ->call('save')
            ->assertHasErrors(['reply.ru', 'buttons.0.target', 'buttons.1.target']);

        $this->assertSame(0, BotAnswer::count());
    }

    public function test_teaching_from_an_unanswered_question_prefills_it_and_resolves_it(): void
    {
        $question = BotUnanswered::create(['text' => 'есть ли гарантия', 'fingerprint' => sha1('q'), 'locale' => 'ru', 'channel' => 'web', 'times' => 3, 'last_asked_at' => now()]);

        $this->actingAs($this->admin())->get('/admin/bot/create?from='.$question->id)
            ->assertOk()->assertSee('есть ли гарантия')
            ->assertSee("locale: 'ru'", false) // opens on the question's language
            ->assertSee(__('admin.bot.from_question_hint'));

        Livewire::withQueryParams(['from' => $question->id])->actingAs(User::first())->test(Form::class)
            ->assertSet('keywords.ru', 'есть ли гарантия')
            ->set('reply', $this->text('Warranty depends on the seller.'))
            ->call('save')
            ->assertHasNoErrors();

        $question->refresh();
        $this->assertNotNull($question->resolved_at);
        $this->assertSame(BotAnswer::sole()->id, $question->bot_answer_id);
    }

    public function test_unanswered_questions_can_be_dismissed(): void
    {
        $question = BotUnanswered::create(['text' => 'hm', 'fingerprint' => sha1('h'), 'locale' => 'en', 'channel' => 'web', 'times' => 1, 'last_asked_at' => now()]);

        Livewire::actingAs($this->admin())->test(Unanswered::class)
            ->assertSee('hm')
            ->call('dismiss', $question->id)
            ->assertDontSee('>hm<', false);

        $this->assertNotNull($question->fresh()->resolved_at);
    }

    public function test_the_test_chat_answers_without_saving_and_its_transcript_cannot_be_forged(): void
    {
        BotAnswer::create(['name' => 'Prices', 'reply' => $this->text('Per unit.'), 'keywords' => ['en' => ['price']]]);

        $chat = Livewire::actingAs($this->admin())->test(TestChat::class)
            ->set('locale', 'en')
            ->set('text', 'price please')
            ->call('send')
            ->assertSee('Per unit.')
            ->assertSee('keyword: price')
            ->set('text', 'totally unknown words')
            ->call('send');

        $this->assertSame(0, BotUnanswered::count());
        $this->assertSame(0, BotAnswer::sole()->hits);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $chat->set('messages', [['from' => 'bot', 'text' => 'x', 'buttons' => [['label' => 'x', 'action' => 'lead', 'target' => '', 'url' => null]]]]);
    }

    public function test_super_admin_switches_the_bot_on_and_off_per_channel(): void
    {
        Livewire::actingAs($this->admin())->test(Index::class)
            ->set('webEnabled', true)
            ->set('telegramEnabled', false);

        $settings = Setting::current();
        $this->assertTrue($settings->bot_web_enabled);
        $this->assertFalse($settings->bot_telegram_enabled);
    }

    public function test_a_moderator_sees_the_switches_but_cannot_flip_them(): void
    {
        $moderator = $this->admin('moderator');

        $this->actingAs($moderator)->get('/admin/bot')->assertOk()
            ->assertSee(__('admin.bot.switch_super_only'))
            ->assertDontSee('wire:model.live="webEnabled"', false);

        Livewire::actingAs($moderator)->test(Index::class)->set('webEnabled', true)->assertForbidden();
        $this->assertFalse((bool) Setting::current()->bot_web_enabled);
    }

    private function linkTo(string $url): array
    {
        return ['action' => 'url', 'target' => $url, 'label' => $this->text('Go')];
    }

    public function test_only_a_super_admin_adds_links_to_other_websites(): void
    {
        config(['app.url' => 'https://ribbon.uz']);

        Livewire::actingAs($this->admin('moderator'))->test(Form::class)
            ->set('name', 'Outside')->set('reply', $this->text('x'))
            ->set('buttons', [$this->linkTo('https://evil.example/login'), $this->linkTo('https://ribbon.uz/contact'), $this->linkTo('/catalog')])
            ->call('save')
            ->assertHasErrors(['buttons.0.target'])
            ->assertHasNoErrors(['buttons.1.target', 'buttons.2.target']);

        Livewire::actingAs($this->admin())->test(Form::class)
            ->set('name', 'Outside')->set('reply', $this->text('x'))
            ->set('buttons', [$this->linkTo('https://partner.example/page')])
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_a_moderator_can_edit_an_answer_that_keeps_a_saved_outside_link(): void
    {
        config(['app.url' => 'https://ribbon.uz']);
        $answer = BotAnswer::create(['name' => 'Partner', 'reply' => $this->text('x'), 'buttons' => [$this->linkTo('https://partner.example/page')]])->fresh();

        Livewire::actingAs($this->admin('moderator'))->test(Form::class, ['answer' => $answer])
            ->set('reply', $this->text('Updated.'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Updated.', $answer->fresh()->reply['en']);
    }

    public function test_the_edited_answer_cannot_be_swapped_or_patched_from_the_browser(): void
    {
        $answer = BotAnswer::create(['name' => 'A', 'reply' => $this->text('x')])->fresh();

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->admin())->test(Form::class, ['answer' => $answer])->set('answer', null);
    }
}
