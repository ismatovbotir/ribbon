<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryParameter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Hand-edited or scanner URLs must never 500 a public page (QA N3-05). A
 * malformed filter param is ignored; the page renders as if it wasn't sent.
 */
class StorefrontJunkQueryParamsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategoryWithNumberFilter(): CategoryParameter
    {
        $category = Category::create([
            'name' => ['uz' => 'Lentalar', 'ru' => 'Ленты', 'en' => 'Ribbons'],
            'slug' => ['uz' => 'lentalar', 'ru' => 'lenty', 'en' => 'ribbons'],
            'is_active' => true,
            'requires_subscription' => false,
        ]);

        return CategoryParameter::create([
            'category_id' => $category->id,
            'name' => ['uz' => 'Eni', 'ru' => 'Ширина', 'en' => 'Width'],
            'type' => 'number',
            'is_required' => false,
            'is_filterable' => true,
        ]);
    }

    #[DataProvider('catalogJunk')]
    public function test_catalog_ignores_malformed_params(string $query): void
    {
        $parameter = $this->makeCategoryWithNumberFilter();

        $this->get('/catalog/lentalar?'.str_replace('{id}', (string) $parameter->id, $query))->assertOk();
    }

    public static function catalogJunk(): array
    {
        return [
            'nested min' => ['min[{id}][]=1'],
            'nested max' => ['max[{id}][]=1'],
            'nested brand' => ['brand[0][0]=1'],
            'scalar min' => ['min=abc'],
            'scalar brand' => ['brand=abc'],
            'array sort' => ['sort[]=newest'],
            'json sort' => ['sort=%7B%22a%22%3A1%7D'],
            'array page' => ['page[]=1'],
        ];
    }

    #[DataProvider('searchJunk')]
    public function test_search_ignores_malformed_params(string $query): void
    {
        $this->get('/search?'.$query)->assertOk();
    }

    public static function searchJunk(): array
    {
        return [
            'array q' => ['q[]=1'],
            'json q' => ['q=%7B%22a%22%3A1%7D'],
            'nested category' => ['q=x&category[0][0]=1'],
            'scalar category' => ['q=x&category=abc'],
        ];
    }

    public function test_result_counts_use_correct_plural_forms(): void
    {
        app()->setLocale('ru');
        $this->assertSame('товар', trans_choice('storefront.catalog.results_word', 1));
        $this->assertSame('товара', trans_choice('storefront.catalog.results_word', 2));
        $this->assertSame('товаров', trans_choice('storefront.catalog.results_word', 5));
        $this->assertSame('Показать 2 результата', trans_choice('storefront.catalog.filters_apply', 2));

        app()->setLocale('en');
        $this->assertSame('product', trans_choice('storefront.catalog.results_word', 1));
        $this->assertSame('Show 4 results', trans_choice('storefront.catalog.filters_apply', 4));

        app()->setLocale('uz');
        $this->assertSame('mahsulot', trans_choice('storefront.catalog.results_word', 5));
    }

    public function test_valid_number_filter_still_applies(): void
    {
        $parameter = $this->makeCategoryWithNumberFilter();

        $this->get("/catalog/lentalar?min[{$parameter->id}]=50")
            ->assertOk()
            ->assertSee('noindex,follow', false);
    }
}
