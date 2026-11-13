<?php

namespace App\Services;

use App\Models\CategoryParameter;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ribbon ↔ TT label compatibility, decided by the Super Admin as one matrix
 * of ribbon Type options × label Material options (table
 * `ribbon_label_compatibility`), never by sellers.
 *
 * A label fits a ribbon when:
 *  - the matrix allows the ribbon's Type with the label's Material, and
 *  - the label is not wider than the ribbon (when both widths are set).
 *
 * Parameters are found by stable code (CategoryParameter::CODE_*), never by
 * category name. Lives in a service so the storefront, the future Telegram
 * Web App and the JSON feed share one rule; every product query uses the
 * shared visibleOnStorefront() scope.
 */
class RibbonLabelCompatibilityService
{
    public const TABLE = 'ribbon_label_compatibility';

    public static function typeParameter(): ?CategoryParameter
    {
        return CategoryParameter::with('options')->where('code', CategoryParameter::CODE_RIBBON_TYPE)->first();
    }

    public static function materialParameter(): ?CategoryParameter
    {
        return CategoryParameter::with('options')->where('code', CategoryParameter::CODE_LABEL_MATERIAL)->first();
    }

    /**
     * Allowed pairs as "typeOptionId:materialOptionId" strings.
     *
     * @return array<int, string>
     */
    public static function pairKeys(): array
    {
        return DB::table(self::TABLE)
            ->get(['ribbon_type_option_id', 'label_material_option_id'])
            ->map(fn ($row) => $row->ribbon_type_option_id.':'.$row->label_material_option_id)
            ->all();
    }

    /**
     * Replaces the whole matrix. Pairs whose ids aren't options of the coded
     * Type / Material parameters are dropped, so a tampered request can't
     * link arbitrary options.
     *
     * @param  array<int, string>  $pairKeys  "typeOptionId:materialOptionId"
     */
    public static function sync(array $pairKeys): void
    {
        $typeIds = self::typeParameter()?->options->pluck('id')->all() ?? [];
        $materialIds = self::materialParameter()?->options->pluck('id')->all() ?? [];
        $now = now();

        $rows = collect($pairKeys)
            ->filter(fn ($key) => is_string($key) && preg_match('/^\d+:\d+$/', $key))
            ->unique()
            ->map(fn (string $key) => array_map('intval', explode(':', $key)))
            ->filter(fn (array $pair) => in_array($pair[0], $typeIds, true) && in_array($pair[1], $materialIds, true))
            ->map(fn (array $pair) => [
                'ribbon_type_option_id' => $pair[0],
                'label_material_option_id' => $pair[1],
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($rows) {
            DB::table(self::TABLE)->delete();

            if ($rows !== []) {
                DB::table(self::TABLE)->insert($rows);
            }
        });
    }

    /**
     * Visible TT labels that fit this ribbon. Empty when the product isn't a
     * ribbon or the Super Admin hasn't allowed any material for its type.
     *
     * @return Collection<int, Product>
     */
    public static function labelsFor(Product $ribbon, int $limit = 6): Collection
    {
        $type = self::typeParameter();
        $material = self::materialParameter();

        if (! $type || ! $material || $ribbon->category_id !== $type->category_id) {
            return collect();
        }

        $typeOptionIds = self::selectedOptionIds($ribbon, $type->id);

        $materialOptionIds = DB::table(self::TABLE)
            ->whereIn('ribbon_type_option_id', $typeOptionIds)
            ->pluck('label_material_option_id')
            ->all();

        if ($materialOptionIds === []) {
            return collect();
        }

        $query = self::listQuery($material->category_id, $ribbon->id)
            ->whereHas('parameterValues', fn (Builder $value) => $value
                ->where('category_parameter_id', $material->id)
                ->whereHas('options', fn (Builder $option) => $option->whereIn('category_parameter_option_id', $materialOptionIds)));

        $ribbonWidth = self::numberValue($ribbon, CategoryParameter::CODE_RIBBON_WIDTH);
        $labelWidthParameterId = CategoryParameter::where('code', CategoryParameter::CODE_LABEL_WIDTH)->value('id');

        if ($ribbonWidth !== null && $labelWidthParameterId) {
            $query->whereHas('parameterValues', fn (Builder $value) => $value
                ->where('category_parameter_id', $labelWidthParameterId)
                ->where('value_number', '<=', $ribbonWidth));
        }

        return $query->limit($limit)->get();
    }

    /**
     * Visible ribbons that fit this TT label (the inverse of labelsFor()).
     *
     * @return Collection<int, Product>
     */
    public static function ribbonsFor(Product $label, int $limit = 6): Collection
    {
        $type = self::typeParameter();
        $material = self::materialParameter();

        if (! $type || ! $material || $label->category_id !== $material->category_id) {
            return collect();
        }

        $materialOptionIds = self::selectedOptionIds($label, $material->id);

        $typeOptionIds = DB::table(self::TABLE)
            ->whereIn('label_material_option_id', $materialOptionIds)
            ->pluck('ribbon_type_option_id')
            ->all();

        if ($typeOptionIds === []) {
            return collect();
        }

        $query = self::listQuery($type->category_id, $label->id)
            ->whereHas('parameterValues', fn (Builder $value) => $value
                ->where('category_parameter_id', $type->id)
                ->whereHas('options', fn (Builder $option) => $option->whereIn('category_parameter_option_id', $typeOptionIds)));

        $labelWidth = self::numberValue($label, CategoryParameter::CODE_LABEL_WIDTH);
        $ribbonWidthParameterId = CategoryParameter::where('code', CategoryParameter::CODE_RIBBON_WIDTH)->value('id');

        if ($labelWidth !== null && $ribbonWidthParameterId) {
            $query->whereHas('parameterValues', fn (Builder $value) => $value
                ->where('category_parameter_id', $ribbonWidthParameterId)
                ->where('value_number', '>=', $labelWidth));
        }

        return $query->limit($limit)->get();
    }

    private static function listQuery(int $categoryId, string $excludeProductId): Builder
    {
        return Product::query()
            ->visibleOnStorefront()
            ->where('category_id', $categoryId)
            ->whereKeyNot($excludeProductId)
            ->with([
                'category',
                'brand',
                'seller',
                'parameterValues.categoryParameter',
                'parameterValues.options.categoryParameterOption',
                'images',
                'prices' => fn ($query) => $query->where('is_vitrin', true),
            ])
            ->orderByDesc('moderated_at')
            ->orderByDesc('created_at');
    }

    /**
     * @return array<int, int>
     */
    private static function selectedOptionIds(Product $product, int $parameterId): array
    {
        $product->loadMissing('parameterValues.options');

        return $product->parameterValues
            ->where('category_parameter_id', $parameterId)
            ->flatMap(fn ($value) => $value->options->pluck('category_parameter_option_id'))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private static function numberValue(Product $product, string $code): ?float
    {
        $product->loadMissing('parameterValues.categoryParameter');

        $value = $product->parameterValues
            ->first(fn ($value) => $value->categoryParameter?->code === $code)
            ?->value_number;

        return $value !== null ? (float) $value : null;
    }
}
