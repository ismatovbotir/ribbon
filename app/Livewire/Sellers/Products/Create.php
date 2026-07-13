<?php

namespace App\Livewire\Sellers\Products;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\Product;
use App\Models\ProductParameterValue;
use App\Models\Seller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    public ?int $categoryId = null;

    public int $brandId = 1;

    /**
     * The only seller-editable piece of `name` — appended to the end of the
     * auto-composed name (see Product::composeLabel()). Everything else
     * about `name`/`slug` is derived, not typed directly.
     */
    public ?string $nameExtra = null;

    /**
     * Keyed by category_parameter_id. text/number values are scalars,
     * select_single is a single option id, select_multiple is an array of
     * option ids.
     *
     * @var array<int, mixed>
     */
    public array $parameterValues = [];

    // ---- Images (up to 4, attached at creation time — see save()) ----

    /**
     * Single-file staging control — one file is added at a time via
     * updatedNewImageUpload(), appended into $stagedImages with a stable
     * key, then cleared. This (rather than a single `multiple` file input
     * bound directly to an array) is what lets the seller build up a set
     * of images incrementally with individual remove, since a plain
     * multi-select file input's selection is replaced (not merged) each
     * time the OS file picker is reopened.
     */
    public $newImageUpload = null;

    /**
     * @var array<int, array{key: string, file: mixed}>
     */
    public array $stagedImages = [];

    public function seller(): Seller
    {
        return Auth::user()->sellerOrFail();
    }

    #[Computed]
    public function categories()
    {
        return Category::where('is_active', true)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function brands()
    {
        return Brand::orderBy('name')->get();
    }

    #[Computed]
    public function selectedCategory(): ?Category
    {
        if (! $this->categoryId) {
            return null;
        }

        return Category::with(['parameters.options'])->find($this->categoryId);
    }

    /**
     * Live preview of the auto-composed `name`, recomputed on every render
     * from the in-progress brand/category-parameter/extra form state — see
     * Product::composeLabel() for the exact composition rule. Not persisted
     * directly; save() recomposes name+slug once more at submit time.
     */
    #[Computed]
    public function previewName(): string
    {
        return Product::composeLabel(
            Brand::find($this->brandId),
            $this->selectedCategory?->parameters ?? [],
            $this->parameterValues,
            $this->nameExtra,
            config('ribbon.locales')[0],
        );
    }

    /**
     * The previous category's parameter values are meaningless once the
     * category changes (different parameter ids entirely) — reset the form
     * rather than carrying stale keys forward.
     */
    public function updatedCategoryId(): void
    {
        $this->parameterValues = [];
        $this->resetErrorBag();

        foreach ($this->selectedCategory?->parameters ?? [] as $parameter) {
            $this->parameterValues[$parameter->id] = $parameter->type === 'select_multiple' ? [] : null;
        }
    }

    /**
     * Validates and stages a single freshly-picked image, then clears the
     * single-file control so the next pick starts fresh. Silently drops
     * the pick once 4 images are already staged — the UI also hides the
     * file input at that point (see create.blade.php), this is the
     * server-side backstop for a stale/tampered request, mirroring how
     * ProductImage::bootProductImage()'s hard cap is a last-resort
     * safety net, not the primary UX.
     */
    public function updatedNewImageUpload(): void
    {
        $this->validateOnly('newImageUpload', [
            'newImageUpload' => ['image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ]);

        if (count($this->stagedImages) >= 4) {
            $this->newImageUpload = null;

            return;
        }

        $this->stagedImages[] = ['key' => (string) Str::uuid(), 'file' => $this->newImageUpload];
        $this->newImageUpload = null;
    }

    public function removeStagedImage(string $key): void
    {
        $this->stagedImages = array_values(array_filter(
            $this->stagedImages,
            fn (array $image) => $image['key'] !== $key,
        ));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function parameterRules(Category $category): array
    {
        $rules = [];

        foreach ($category->parameters as $parameter) {
            $key = "parameterValues.{$parameter->id}";
            $requiredRule = $parameter->is_required ? 'required' : 'nullable';

            $rules[$key] = match ($parameter->type) {
                'text' => [$requiredRule, 'string', 'max:255'],
                'number' => [$requiredRule, 'numeric'],
                'select_single' => [
                    $requiredRule,
                    Rule::exists('category_parameter_options', 'id')->where('category_parameter_id', $parameter->id),
                ],
                'select_multiple' => [$parameter->is_required ? 'required' : 'nullable', 'array'],
                default => ['nullable'],
            };

            if ($parameter->type === 'select_multiple') {
                $rules["{$key}.*"] = [
                    Rule::exists('category_parameter_options', 'id')->where('category_parameter_id', $parameter->id),
                ];
            }
        }

        return $rules;
    }

    public function save(): void
    {
        $rules = [
            'categoryId' => ['required', Rule::exists('categories', 'id')->where('is_active', true)],
            'brandId' => ['required', 'exists:brands,id'],
            'nameExtra' => ['nullable', 'string', 'max:255'],
        ];

        $category = $this->selectedCategory;

        if ($category) {
            $rules = array_merge($rules, $this->parameterRules($category));
        }

        $this->validate($rules);

        $seller = $this->seller();

        $product = DB::transaction(function () use ($seller, $category) {
            $composed = Product::composeNameAndSlug(
                Brand::find($this->brandId),
                $category->parameters,
                $this->parameterValues,
                $this->nameExtra,
            );

            // The pcs price row is auto-created by Product::bootProduct() —
            // name/slug are precomputed above rather than left to that
            // hook's fallback auto-generation, since we have the full
            // brand+parameter+extra context here.
            $product = Product::create([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'brand_id' => $this->brandId,
                'name' => $composed['name'],
                'name_extra' => $this->nameExtra,
                'slug' => $composed['slug'],
            ]);

            foreach ($category->parameters as $parameter) {
                $this->persistParameterValue($product, $parameter);
            }

            // Defensive re-slice to 4 even though updatedNewImageUpload()
            // already stops staging past that point — a stale/tampered
            // request shouldn't be able to exceed the model's hard cap
            // (ProductImage::bootProductImage()) either way.
            foreach (array_slice($this->stagedImages, 0, 4) as $index => $staged) {
                $product->images()->create([
                    'path' => $staged['file']->store('items', 'public'),
                    'sort_order' => $index,
                ]);
            }

            return $product;
        });

        session()->flash('status', __('sellers.products.create.success'));

        $this->redirectRoute('seller.products.edit', $product, navigate: true);
    }

    protected function persistParameterValue(Product $product, CategoryParameter $parameter): void
    {
        $raw = $this->parameterValues[$parameter->id] ?? null;
        $isBlank = $raw === null || $raw === '' || (is_array($raw) && $raw === []);

        // Optional parameters left blank simply get no
        // ProductParameterValue row at all.
        if ($isBlank) {
            return;
        }

        $value = new ProductParameterValue([
            'product_id' => $product->id,
            'category_parameter_id' => $parameter->id,
        ]);

        // The model's own saving hook nulls out whichever of these doesn't
        // match the parameter's type regardless, but send the right one.
        if ($parameter->type === 'text') {
            $value->value_text = $raw;
        } elseif ($parameter->type === 'number') {
            $value->value_number = $raw;
        }

        $value->save();

        if ($parameter->type === 'select_single') {
            $value->options()->create(['category_parameter_option_id' => $raw]);
        } elseif ($parameter->type === 'select_multiple') {
            foreach ($raw as $optionId) {
                $value->options()->create(['category_parameter_option_id' => $optionId]);
            }
        }
    }

    public function render()
    {
        return view('livewire.sellers.products.create')
            ->layout('layouts.seller', [
                'title' => __('sellers.products.create.title'),
                'breadcrumb' => [
                    ['label' => __('sellers.nav.products'), 'url' => route('seller.products.index')],
                    ['label' => __('sellers.products.create.title')],
                ],
            ]);
    }
}
