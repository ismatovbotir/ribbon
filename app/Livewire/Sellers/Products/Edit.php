<?php

namespace App\Livewire\Sellers\Products;

use App\Models\Brand;
use App\Models\CategoryParameter;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductParameterValue;
use App\Models\ProductPrice;
use App\Models\Seller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The seller product edit screen: four logically distinct sections on one
 * page (Category & Details, Specifications, Pricing, Images), per
 * docs/design/05-form-patterns.md's "titled bordered sections" guidance.
 * The Pricing section follows docs/design/07-product-pricing-editor.md
 * closely.
 */
class Edit extends Component
{
    use WithFileUploads;

    public Product $product;

    // ---- Category & Details ----
    public int $brandId = 1;

    /**
     * The only seller-editable piece of `name` — appended to the end of the
     * auto-composed name (see Product::composeLabel()). Everything else
     * about `name`/`slug` is derived, not typed directly.
     */
    public ?string $nameExtra = null;

    // ---- Specifications ----
    /** @var array<int, mixed> */
    public array $parameterValues = [];

    // ---- Pricing ----
    /**
     * Keyed by unit ('pcs'/'pack'/'box') for every currently-*enabled* row.
     * Edits here auto-persist on blur via the generic updated() hook below
     * — the design spec only calls for an explicit Save/Cancel pair on the
     * ghost-row "enable" flow, not on already-enabled rows.
     *
     * @var array<string, array{qty_in_pcs: int|null, price: string|null}>
     */
    public array $priceForm = [];

    /**
     * Holds in-progress values for a pack/box row being enabled but not yet
     * saved — kept separate from priceForm so Cancel can discard without
     * ever creating a product_prices row.
     *
     * @var array<string, array{qty_in_pcs: int|null, price: string|null}>
     */
    public array $newRowForm = [
        'pack' => ['qty_in_pcs' => null, 'price' => null],
        'box' => ['qty_in_pcs' => null, 'price' => null],
    ];

    /** @var array<string, bool> */
    public array $addingUnit = ['pack' => false, 'box' => false];

    public ?string $removingUnit = null;

    public bool $showRemoveConfirm = false;

    // ---- Images ----

    /**
     * Single-file staging control, mirroring
     * Sellers\Products\Create::$newImageUpload — but here each pick is
     * persisted immediately as a ProductImage row (see
     * updatedNewImageUpload()) rather than staged client-side, since this
     * screen is editing an already-existing product.
     */
    public $newImageUpload = null;

    /**
     * Route-model-bound by the product's ULID. Authorization is a 404 (not
     * a redirect) when the product isn't this seller's own — a redirect
     * would leak whether a given product id exists at all to someone
     * probing another seller's URLs.
     */
    public function mount(Product $product): void
    {
        $seller = $this->seller();

        abort_unless($product->seller_id === $seller->id, 404);

        $this->product = $product->load(['category.parameters.options', 'parameterValues.options', 'prices', 'images']);

        $this->brandId = $product->brand_id;
        $this->nameExtra = $product->name_extra;

        $this->fillParameterValuesForm();
        $this->fillPriceForm();
    }

    public function seller(): Seller
    {
        return Auth::user()->sellerOrFail();
    }

    protected function fillParameterValuesForm(): void
    {
        $this->parameterValues = [];

        foreach ($this->product->category->parameters as $parameter) {
            $existing = $this->product->parameterValues->firstWhere('category_parameter_id', $parameter->id);

            $this->parameterValues[$parameter->id] = match (true) {
                ! $existing => $parameter->type === 'select_multiple' ? [] : null,
                $parameter->type === 'text' => $existing->value_text,
                // decimal:2/3 casts return numeric strings like "40.000" —
                // +$x strips trailing zeros/decimal noise for a clean
                // number-input display.
                $parameter->type === 'number' => $existing->value_number === null ? null : (string) ($existing->value_number + 0),
                $parameter->type === 'select_single' => optional($existing->options->first())->category_parameter_option_id,
                $parameter->type === 'select_multiple' => $existing->options->pluck('category_parameter_option_id')->all(),
                default => null,
            };
        }
    }

    protected function fillPriceForm(): void
    {
        $this->priceForm = [];

        foreach ($this->product->prices as $price) {
            $this->priceForm[$price->unit] = [
                'qty_in_pcs' => $price->qty_in_pcs,
                'price' => (string) $price->price,
            ];
        }
    }

    // ------------------------------------------------------------------
    // Category & Details
    // ------------------------------------------------------------------

    #[Computed]
    public function brands()
    {
        return Brand::orderBy('name')->get();
    }

    /**
     * Live preview of the auto-composed `name`, recomputed on every render
     * from the in-progress brand/category-parameter/extra form state — see
     * Product::composeLabel() for the exact composition rule.
     */
    #[Computed]
    public function previewName(): string
    {
        return Product::composeLabel(
            Brand::find($this->brandId),
            $this->product->category->parameters,
            $this->parameterValues,
            $this->nameExtra,
            config('ribbon.locales')[0],
        );
    }

    /**
     * Recompose `name`/`slug` from the current in-memory form state
     * (brandId + parameterValues + nameExtra), excluding this product's own
     * id from the slug's collision check. Shared by saveDetails() and
     * saveSpecifications() since the composed name depends on both
     * sections' data — see composeNameAndSlug()'s doc comment.
     *
     * @return array{name: string, slug: array<string, string>}
     */
    protected function composeNameAndSlug(): array
    {
        return Product::composeNameAndSlug(
            Brand::find($this->brandId),
            $this->product->category->parameters,
            $this->parameterValues,
            $this->nameExtra,
            $this->product->id,
        );
    }

    public function saveDetails(): void
    {
        $this->validate([
            'brandId' => ['required', 'exists:brands,id'],
            'nameExtra' => ['nullable', 'string', 'max:255'],
        ]);

        $composed = $this->composeNameAndSlug();

        $this->product->update([
            'brand_id' => $this->brandId,
            'name_extra' => $this->nameExtra,
            'name' => $composed['name'],
            // Deliberate departure from Category's slug (frozen after
            // creation): this product's auto-derived name/slug are kept
            // accurate as specs change, so slug is recomputed here too, not
            // just at creation time.
            'slug' => $composed['slug'],
        ]);

        session()->flash('status', __('sellers.products.edit.details_saved'));
    }

    // ------------------------------------------------------------------
    // Specifications
    // ------------------------------------------------------------------

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function parameterRules(): array
    {
        $rules = [];

        foreach ($this->product->category->parameters as $parameter) {
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

    public function saveSpecifications(): void
    {
        $this->validate($this->parameterRules());

        DB::transaction(function () {
            foreach ($this->product->category->parameters as $parameter) {
                $this->persistParameterValue($parameter);
            }
        });

        $this->product->unsetRelation('parameterValues');
        $this->product->load('parameterValues.options');

        // The composed name/slug fold in parameter display values too, so
        // a specifications change must re-derive them here — same
        // deliberate not-frozen-after-creation departure noted in
        // saveDetails().
        $composed = $this->composeNameAndSlug();

        $this->product->update([
            'name' => $composed['name'],
            'slug' => $composed['slug'],
        ]);

        session()->flash('status', __('sellers.products.edit.specifications_saved'));
    }

    /**
     * Update-in-place by the (product_id, category_parameter_id) unique
     * key — firstOrNew() guarantees this never duplicates an existing row,
     * it only ever updates it or creates the first one.
     */
    protected function persistParameterValue(CategoryParameter $parameter): void
    {
        $raw = $this->parameterValues[$parameter->id] ?? null;
        $isBlank = $raw === null || $raw === '' || (is_array($raw) && $raw === []);

        if ($isBlank) {
            ProductParameterValue::where('product_id', $this->product->id)
                ->where('category_parameter_id', $parameter->id)
                ->delete();

            return;
        }

        $value = ProductParameterValue::firstOrNew([
            'product_id' => $this->product->id,
            'category_parameter_id' => $parameter->id,
        ]);

        $value->value_text = $parameter->type === 'text' ? $raw : null;
        $value->value_number = $parameter->type === 'number' ? $raw : null;
        $value->save();

        if (in_array($parameter->type, ['select_single', 'select_multiple'], true)) {
            $value->options()->delete();

            $optionIds = $parameter->type === 'select_single' ? [$raw] : $raw;

            foreach ($optionIds as $optionId) {
                $value->options()->create(['category_parameter_option_id' => $optionId]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Pricing — docs/design/07-product-pricing-editor.md
    // ------------------------------------------------------------------

    /**
     * @return array<string, ProductPrice>
     */
    #[Computed]
    public function pricesByUnit(): array
    {
        return $this->product->prices()->get()->keyBy('unit')->all();
    }

    #[Computed]
    public function vitrinPrice(): ?ProductPrice
    {
        return $this->product->prices()->where('is_vitrin', true)->first();
    }

    /**
     * Generic Livewire lifecycle hook, fires after any property syncs from
     * the browser. Used to auto-persist an already-enabled row's qty/price
     * on blur without a separate explicit Save button — per the design
     * spec, only the ghost-row "enable" flow gets an explicit Save/Cancel
     * pair (so an abandoned attempt never creates a stray price: 0 row).
     */
    public function updated($name, $value): void
    {
        if (preg_match('/^priceForm\.(pcs|pack|box)\.(qty_in_pcs|price)$/', (string) $name, $matches)) {
            $this->savePriceField($matches[1], $matches[2]);
        }
    }

    protected function savePriceField(string $unit, string $field): void
    {
        $row = $this->product->prices()->where('unit', $unit)->first();

        if (! $row) {
            return;
        }

        $key = "priceForm.{$unit}.{$field}";

        $this->validate([
            $key => $field === 'price' ? ['required', 'numeric', 'min:0.01'] : ['required', 'integer', 'min:1'],
        ]);

        $row->{$field} = $this->priceForm[$unit][$field];
        $row->save();

        $this->product->unsetRelation('prices');
    }

    public function setVitrin(string $unit): void
    {
        $row = $this->product->prices()->where('unit', $unit)->first();

        // Defensive no-op — the UI already hides/disables this action for
        // a 0.00 price or an already-vitrin row.
        if (! $row || (float) $row->price <= 0) {
            return;
        }

        $row->makeVitrin();
        $this->product->unsetRelation('prices');
    }

    public function startEnable(string $unit): void
    {
        if (! in_array($unit, ['pack', 'box'], true)) {
            return;
        }

        $this->addingUnit[$unit] = true;
        $this->newRowForm[$unit] = ['qty_in_pcs' => null, 'price' => null];
        $this->resetErrorBag();
    }

    /**
     * Collapses back to the ghost row without ever creating a price
     * record — see docs/design/07's explicit "Cancel must not create a
     * stray row" requirement.
     */
    public function cancelEnable(string $unit): void
    {
        $this->addingUnit[$unit] = false;
        $this->newRowForm[$unit] = ['qty_in_pcs' => null, 'price' => null];
        $this->resetErrorBag();
    }

    public function saveEnable(string $unit): void
    {
        if (! in_array($unit, ['pack', 'box'], true)) {
            return;
        }

        $this->validate([
            "newRowForm.{$unit}.qty_in_pcs" => ['required', 'integer', 'min:1'],
            "newRowForm.{$unit}.price" => ['required', 'numeric', 'min:0.01'],
        ]);

        $row = $this->product->prices()->create([
            'unit' => $unit,
            'qty_in_pcs' => $this->newRowForm[$unit]['qty_in_pcs'],
            'price' => $this->newRowForm[$unit]['price'],
            'is_vitrin' => false,
        ]);

        $this->priceForm[$unit] = [
            'qty_in_pcs' => $row->qty_in_pcs,
            'price' => (string) $row->price,
        ];

        $this->addingUnit[$unit] = false;
        $this->product->unsetRelation('prices');
    }

    public function confirmRemove(string $unit): void
    {
        $row = $this->product->prices()->where('unit', $unit)->first();

        // pcs is never removable (hidden, not disabled, in the view — its
        // undeletability is structural, see docs/design/07). A vitrin row
        // is UI-guarded here too, even though the model now auto-reassigns
        // is_vitrin to pcs on delete as a belt-and-braces backstop.
        if (! $row || $unit === 'pcs' || $row->is_vitrin) {
            return;
        }

        $this->removingUnit = $unit;
        $this->showRemoveConfirm = true;
    }

    public function cancelRemove(): void
    {
        $this->removingUnit = null;
        $this->showRemoveConfirm = false;
    }

    public function removeUnit(): void
    {
        if (! $this->removingUnit) {
            return;
        }

        $row = $this->product->prices()->where('unit', $this->removingUnit)->first();
        $row?->delete();

        unset($this->priceForm[$this->removingUnit]);

        $this->product->unsetRelation('prices');
        $this->removingUnit = null;
        $this->showRemoveConfirm = false;
    }

    // ------------------------------------------------------------------
    // Images
    // ------------------------------------------------------------------

    /**
     * @return Collection<int, ProductImage>
     */
    #[Computed]
    public function images()
    {
        return $this->product->images()->get();
    }

    /**
     * Validates and immediately persists a single freshly-picked image as
     * the next ProductImage row (sort_order = current max + 1), then
     * clears the single-file control so the next pick starts fresh. Drops
     * the pick once 4 images already exist — the UI also hides the file
     * input at that point (see edit.blade.php), this is the server-side
     * backstop for a stale/tampered request; the model's own hard cap
     * (ProductImage::bootProductImage()) is a last-resort safety net, not
     * the primary UX, per this task's instructions.
     */
    public function updatedNewImageUpload(): void
    {
        $this->validateOnly('newImageUpload', [
            'newImageUpload' => ['image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ]);

        if ($this->product->images()->count() >= 4) {
            $this->newImageUpload = null;

            return;
        }

        $nextSortOrder = (int) ($this->product->images()->max('sort_order') ?? -1) + 1;

        $this->product->images()->create([
            'path' => $this->newImageUpload->store('items', 'public'),
            'sort_order' => $nextSortOrder,
        ]);

        $this->newImageUpload = null;
        $this->product->unsetRelation('images');
    }

    public function removeImage(int $imageId): void
    {
        $image = $this->product->images()->where('id', $imageId)->first();

        if (! $image) {
            return;
        }

        $image->delete();

        // Re-normalize sort_order to a clean, gapless 0..n-1 sequence so
        // the "lowest sort_order = primary" convention and the up/down
        // reorder buttons both keep behaving predictably after a removal
        // from the middle of the set.
        $this->renumberImages();

        $this->product->unsetRelation('images');
    }

    /**
     * Swaps this image's sort_order with its immediate predecessor —
     * moving it "up" (toward becoming the primary image at sort_order 0).
     * No-op if it's already first.
     */
    public function moveImageUp(int $imageId): void
    {
        $images = $this->product->images()->get();
        $index = $images->search(fn (ProductImage $image) => $image->id === $imageId);

        if ($index === false || $index === 0) {
            return;
        }

        $this->swapImageOrder($images[$index], $images[$index - 1]);
    }

    /**
     * Mirrors moveImageUp() one position the other direction. No-op if
     * it's already last.
     */
    public function moveImageDown(int $imageId): void
    {
        $images = $this->product->images()->get();
        $index = $images->search(fn (ProductImage $image) => $image->id === $imageId);

        if ($index === false || $index === $images->count() - 1) {
            return;
        }

        $this->swapImageOrder($images[$index], $images[$index + 1]);
    }

    protected function swapImageOrder(ProductImage $a, ProductImage $b): void
    {
        [$aOrder, $bOrder] = [$a->sort_order, $b->sort_order];

        $a->update(['sort_order' => $bOrder]);
        $b->update(['sort_order' => $aOrder]);

        $this->product->unsetRelation('images');
    }

    protected function renumberImages(): void
    {
        foreach ($this->product->images()->get() as $index => $image) {
            if ($image->sort_order !== $index) {
                $image->update(['sort_order' => $index]);
            }
        }
    }

    public function render()
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('ribbon.locales')[0];
        $categoryName = $this->product->category->name[$locale] ?? $this->product->category->name[$fallbackLocale] ?? '';
        $title = $this->product->name ?: $categoryName;

        return view('livewire.sellers.products.edit')
            ->layout('layouts.seller', [
                'title' => $title,
                'breadcrumb' => [
                    ['label' => __('sellers.nav.products'), 'url' => route('seller.products.index')],
                    ['label' => $title],
                ],
            ]);
    }
}
