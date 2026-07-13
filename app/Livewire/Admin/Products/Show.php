<?php

namespace App\Livewire\Admin\Products;

use App\Models\Product;
use App\Models\ProductParameterValue;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public Product $product;

    public bool $showRejectForm = false;

    public string $rejectReason = '';

    public bool $showSuspendForm = false;

    public string $suspendReason = '';

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    /**
     * This product's filled-in category parameter values, rendered as
     * "Parameter label: value" pairs. Only reads persisted rows (unlike
     * Product::composeLabel(), which operates on raw in-progress form
     * input), so it resolves each row's human-readable value locally
     * rather than reusing composeLabel() directly — but follows the exact
     * same translated-option-label resolution rule (current locale falling
     * back to the default locale) for select_single/select_multiple types.
     *
     * @return array<int, array{label: string, value: string}>
     */
    #[Computed]
    public function parameterRows(): array
    {
        $defaultLocale = config('ribbon.locales')[0];
        $locale = app()->getLocale();

        $values = $this->product->parameterValues()
            ->with(['categoryParameter', 'options.categoryParameterOption'])
            ->get();

        return $values
            ->sortBy(fn (ProductParameterValue $value) => $value->categoryParameter?->sort_order ?? 0)
            ->map(function (ProductParameterValue $value) use ($locale, $defaultLocale) {
                $parameter = $value->categoryParameter;

                if (! $parameter) {
                    return null;
                }

                $label = $parameter->name[$locale] ?? $parameter->name[$defaultLocale] ?? '';

                $display = match ($parameter->type) {
                    'text' => (string) $value->value_text,
                    'number' => $value->value_number !== null
                        ? ((string) ((float) $value->value_number + 0)).($parameter->unit ?? '')
                        : '',
                    'select_single', 'select_multiple' => $value->options
                        ->map(function ($row) use ($locale, $defaultLocale) {
                            $option = $row->categoryParameterOption;

                            return $option ? ($option->value[$locale] ?? $option->value[$defaultLocale] ?? '') : '';
                        })
                        ->filter(fn ($label) => $label !== '')
                        ->implode(', '),
                    default => '',
                };

                if ($display === '') {
                    return null;
                }

                return ['label' => $label, 'value' => $display];
            })
            ->filter()
            ->values()
            ->all();
    }

    #[Computed]
    public function vitrinPrice(): ?ProductPrice
    {
        return $this->product->prices()->where('is_vitrin', true)->first();
    }

    /**
     * The admin performing this action — mirrors Admin\Sellers\Show::
     * actingAdmin() exactly. This component only ever renders behind the
     * `admin.auth` middleware, which already guarantees Auth::user() is
     * set and holds an admin role.
     */
    protected function actingAdmin(): User
    {
        return Auth::user();
    }

    public function approve(): void
    {
        $this->product->approve($this->actingAdmin());

        session()->flash('status', 'Product approved.');
    }

    public function openRejectForm(): void
    {
        $this->showRejectForm = true;
    }

    public function cancelReject(): void
    {
        $this->showRejectForm = false;
        $this->rejectReason = '';
        $this->resetErrorBag('rejectReason');
    }

    public function reject(): void
    {
        $this->validate([
            'rejectReason' => ['required', 'string', 'max:1000'],
        ]);

        $this->product->reject($this->actingAdmin(), $this->rejectReason);

        $this->showRejectForm = false;
        $this->rejectReason = '';

        session()->flash('status', 'Product rejected.');
    }

    public function openSuspendForm(): void
    {
        $this->showSuspendForm = true;
    }

    public function cancelSuspend(): void
    {
        $this->showSuspendForm = false;
        $this->suspendReason = '';
        $this->resetErrorBag('suspendReason');
    }

    /**
     * Block an already-approved product. Distinct action from reject() —
     * see Product::suspend()'s docblock for why these aren't the same
     * thing.
     */
    public function suspend(): void
    {
        $this->validate([
            'suspendReason' => ['required', 'string', 'max:1000'],
        ]);

        $this->product->suspend($this->actingAdmin(), $this->suspendReason);

        $this->showSuspendForm = false;
        $this->suspendReason = '';

        session()->flash('status', 'Product blocked.');
    }

    public function render()
    {
        return view('livewire.admin.products.show', [
            'defaultLocale' => config('ribbon.locales')[0],
        ])->layout('layouts.admin', [
            'title' => $this->product->name,
            'breadcrumb' => [
                ['label' => 'Products', 'url' => route('admin.products.index')],
                ['label' => $this->product->name ?? 'Product'],
            ],
        ]);
    }
}
