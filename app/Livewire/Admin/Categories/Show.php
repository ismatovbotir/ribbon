<?php

namespace App\Livewire\Admin\Categories;

use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\CategoryParameterOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    public Category $category;

    // ---- Category details card (deliberately understated — see doc 06) ----

    /** @var array<string, string> */
    public array $catName = [];

    public bool $catIsActive = true;

    /**
     * Staged image replacement — jpg/png only, max 1MB (see
     * saveCategoryDetails()). Stored under `categories/` on the `public`
     * disk once saved.
     */
    public $catImageUpload = null;

    public ?string $catExistingImagePath = null;

    // Page-level "preview language" toggle for the parameter list — only
    // changes which locale's text the list displays, has no bearing on
    // editing (distinct from the drawer's section-scoped edit locale tabs).
    public string $previewLocale = '';

    // ---- Add/Edit Parameter drawer ----

    public bool $showDrawer = false;

    public ?int $editingParameterId = null;

    /** @var array<string, string> */
    public array $paramName = [];

    public string $paramType = 'text';

    public ?string $paramUnit = null;

    public bool $paramRequired = false;

    public bool $paramFilterable = true;

    /**
     * @var array<int, array{key: string, id: ?int, value: array<string, string>}>
     */
    public array $options = [];

    // ---- Delete confirmation modal ----

    public bool $showDeleteConfirm = false;

    public ?int $deletingParameterId = null;

    public const TYPE_LABELS = [
        'text' => 'Text',
        'number' => 'Number',
        'select_single' => 'Single choice',
        'select_multiple' => 'Multiple choice',
    ];

    public function mount(Category $category): void
    {
        $this->category = $category;
        $this->previewLocale = config('ribbon.locales')[0];
        $this->resetCategoryDetailsForm();
    }

    protected function resetCategoryDetailsForm(): void
    {
        $this->catName = $this->category->name;
        $this->catIsActive = $this->category->is_active;
        $this->catImageUpload = null;
        $this->catExistingImagePath = $this->category->image_path;
    }

    /**
     * Marks the category image for removal on save — clears both the
     * staged upload (if any) and the existing persisted path.
     */
    public function removeCategoryImage(): void
    {
        $this->catImageUpload = null;
        $this->catExistingImagePath = null;
    }

    public function saveCategoryDetails(): void
    {
        $locales = config('ribbon.locales');
        $rules = [
            'catIsActive' => ['boolean'],
            'catImageUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ];

        foreach ($locales as $locale) {
            $rules["catName.{$locale}"] = ['required', 'string', 'max:120'];
        }

        $this->validate($rules);

        $imagePath = $this->catImageUpload
            ? $this->catImageUpload->store('categories', 'public')
            : $this->catExistingImagePath;

        // Slug is intentionally untouched here — it's generated once at
        // creation time and stays stable across renames (slugs back URLs;
        // silently changing them on a name edit would break links).
        $this->category->update([
            'name' => $this->catName,
            'is_active' => $this->catIsActive,
            'image_path' => $imagePath,
        ]);

        $this->catImageUpload = null;
        $this->catExistingImagePath = $this->category->image_path;

        session()->flash('status', 'Category details saved.');
    }

    #[Computed]
    public function incompleteCategoryLocales(): array
    {
        return collect(config('ribbon.locales'))
            ->filter(fn (string $locale) => blank($this->catName[$locale] ?? null))
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Parameters list
    // ------------------------------------------------------------------

    #[Computed]
    public function parameterStats(): array
    {
        $parameters = $this->category->parameters;

        return [
            'total' => $parameters->count(),
            'required' => $parameters->where('is_required', true)->count(),
            'filterable' => $parameters->where('is_filterable', true)->count(),
        ];
    }

    /**
     * Persist a new parameter order after a drag-and-drop reorder in the
     * list (native HTML5 drag/drop + Alpine, no external sortable JS
     * dependency — see resources/views/livewire/admin/categories/show.blade.php).
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderParameters(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            CategoryParameter::where('id', (int) $id)
                ->where('category_id', $this->category->id)
                ->update(['sort_order' => $index]);
        }

        $this->category->unsetRelation('parameters');
    }

    public function duplicateParameter(int $parameterId): void
    {
        $original = $this->category->parameters()->with('options')->findOrFail($parameterId);

        DB::transaction(function () use ($original) {
            $names = $original->name;
            $defaultLocale = config('ribbon.locales')[0];
            $names[$defaultLocale] = trim(($names[$defaultLocale] ?? '').' (copy)');

            $copy = $original->replicate(['sort_order']);
            $copy->name = $names;
            $copy->sort_order = (int) ($this->category->parameters()->max('sort_order') ?? 0) + 1;
            $copy->save();

            foreach ($original->options as $option) {
                $newOption = $option->replicate(['category_parameter_id']);
                $newOption->category_parameter_id = $copy->id;
                $newOption->save();
            }
        });

        $this->category->unsetRelation('parameters');
    }

    // ------------------------------------------------------------------
    // Add/Edit Parameter drawer
    // ------------------------------------------------------------------

    public function openCreateDrawer(): void
    {
        $this->editingParameterId = null;
        $this->resetParameterForm();
        $this->showDrawer = true;
    }

    public function openEditDrawer(int $parameterId): void
    {
        $parameter = $this->category->parameters()->with('options')->findOrFail($parameterId);

        $this->editingParameterId = $parameter->id;
        $this->paramName = $this->fillLocales($parameter->name);
        $this->paramType = $parameter->type;
        $this->paramUnit = $parameter->unit;
        $this->paramRequired = $parameter->is_required;
        $this->paramFilterable = $parameter->is_filterable;
        $this->options = $parameter->options
            ->sortBy('sort_order')
            ->map(fn (CategoryParameterOption $option) => [
                'key' => (string) $option->id,
                'id' => $option->id,
                'value' => $this->fillLocales($option->value),
            ])
            ->values()
            ->all();

        $this->resetErrorBag();
        $this->showDrawer = true;
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->editingParameterId = null;
        $this->resetParameterForm();
        $this->resetErrorBag();
    }

    protected function resetParameterForm(): void
    {
        $this->paramName = array_fill_keys(config('ribbon.locales'), '');
        $this->paramType = 'text';
        $this->paramUnit = null;
        $this->paramRequired = false;
        $this->paramFilterable = true;
        $this->options = [];
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    protected function fillLocales(array $values): array
    {
        return collect(config('ribbon.locales'))
            ->mapWithKeys(fn (string $locale) => [$locale => $values[$locale] ?? ''])
            ->all();
    }

    public function addOption(): void
    {
        $this->options[] = [
            'key' => (string) Str::uuid(),
            'id' => null,
            'value' => array_fill_keys(config('ribbon.locales'), ''),
        ];
    }

    public function removeOption(string $key): void
    {
        $this->options = array_values(array_filter(
            $this->options,
            fn (array $option) => $option['key'] !== $key,
        ));
    }

    /**
     * Type is locked once the parameter has been saved AND has at least
     * one product_parameter_values row referencing it (docs/design/06).
     * No ProductParameterValue Eloquent model exists yet, so this checks
     * the table directly — a real check, not a stub, it just always
     * evaluates false today since no products/sellers flow exists yet.
     */
    public function isTypeLocked(?int $parameterId): bool
    {
        if (! $parameterId) {
            return false;
        }

        return DB::table('product_parameter_values')
            ->where('category_parameter_id', $parameterId)
            ->exists();
    }

    #[Computed]
    public function incompleteParameterLocales(): array
    {
        $locales = collect(config('ribbon.locales'))
            ->filter(fn (string $locale) => blank($this->paramName[$locale] ?? null));

        foreach ($this->options as $option) {
            foreach (config('ribbon.locales') as $locale) {
                if (blank($option['value'][$locale] ?? null)) {
                    $locales->push($locale);
                }
            }
        }

        return $locales->unique()->values()->all();
    }

    public function saveParameter(): void
    {
        $locales = config('ribbon.locales');
        $isSelectType = in_array($this->paramType, ['select_single', 'select_multiple'], true);
        $typeLocked = $this->isTypeLocked($this->editingParameterId);

        $rules = [
            'paramType' => ['required', Rule::in(array_keys(self::TYPE_LABELS))],
            'paramUnit' => ['nullable', 'string', 'max:32'],
            'paramRequired' => ['boolean'],
            'paramFilterable' => ['boolean'],
        ];

        foreach ($locales as $locale) {
            $rules["paramName.{$locale}"] = ['required', 'string', 'max:120'];
        }

        if ($isSelectType) {
            $rules['options'] = ['array', 'min:2'];

            foreach ($this->options as $index => $option) {
                foreach ($locales as $locale) {
                    $rules["options.{$index}.value.{$locale}"] = ['required', 'string', 'max:120'];
                }
            }
        }

        $this->validate($rules, [
            'options.min' => 'Add at least 2 options.',
        ]);

        DB::transaction(function () use ($typeLocked) {
            if ($this->editingParameterId) {
                $parameter = CategoryParameter::findOrFail($this->editingParameterId);
            } else {
                $parameter = new CategoryParameter;
                $parameter->category_id = $this->category->id;
                $parameter->sort_order = (int) ($this->category->parameters()->max('sort_order') ?? -1) + 1;
            }

            $parameter->name = $this->paramName;

            // Type can't change once locked — silently keep the persisted
            // value even if the (disabled) select somehow posted a
            // different one, rather than trusting client state.
            if (! $typeLocked) {
                $parameter->type = $this->paramType;
            }

            $parameter->unit = $parameter->type === 'number' ? $this->paramUnit : null;
            $parameter->is_required = $this->paramRequired;
            $parameter->is_filterable = $this->paramFilterable;
            $parameter->save();

            if (in_array($parameter->type, ['select_single', 'select_multiple'], true)) {
                $keepIds = [];

                foreach ($this->options as $index => $option) {
                    $optionModel = ! empty($option['id'])
                        ? CategoryParameterOption::find($option['id'])
                        : null;

                    $optionModel ??= new CategoryParameterOption;
                    $optionModel->category_parameter_id = $parameter->id;
                    $optionModel->value = $option['value'];
                    $optionModel->sort_order = $index;
                    $optionModel->save();

                    $keepIds[] = $optionModel->id;
                }

                CategoryParameterOption::where('category_parameter_id', $parameter->id)
                    ->whereNotIn('id', $keepIds)
                    ->delete();
            } else {
                $parameter->options()->delete();
            }
        });

        $this->category->unsetRelation('parameters');
        $this->closeDrawer();
        session()->flash('status', 'Parameter saved.');
    }

    // ------------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------------

    public function confirmDeleteParameter(int $parameterId): void
    {
        $this->deletingParameterId = $parameterId;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
        $this->deletingParameterId = null;
    }

    #[Computed]
    public function deletingParameter(): ?CategoryParameter
    {
        if (! $this->deletingParameterId) {
            return null;
        }

        return $this->category->parameters->firstWhere('id', $this->deletingParameterId);
    }

    #[Computed]
    public function deleteAffectedProductCount(): int
    {
        if (! $this->deletingParameterId) {
            return 0;
        }

        return DB::table('product_parameter_values')
            ->where('category_parameter_id', $this->deletingParameterId)
            ->distinct('product_id')
            ->count('product_id');
    }

    public function deleteParameter(): void
    {
        if ($this->deletingParameterId) {
            CategoryParameter::where('id', $this->deletingParameterId)->delete();
        }

        $this->category->unsetRelation('parameters');
        $this->showDeleteConfirm = false;
        $this->deletingParameterId = null;

        session()->flash('status', 'Parameter deleted.');
    }

    public function render()
    {
        return view('livewire.admin.categories.show', [
            'defaultLocale' => config('ribbon.locales')[0],
            'typeLabels' => self::TYPE_LABELS,
        ])->layout('layouts.admin', [
            'title' => $this->category->name[config('ribbon.locales')[0]] ?? 'Category',
            'breadcrumb' => [
                ['label' => 'Categories', 'url' => route('admin.categories.index')],
                ['label' => $this->category->name[config('ribbon.locales')[0]] ?? 'Category'],
            ],
        ]);
    }
}
