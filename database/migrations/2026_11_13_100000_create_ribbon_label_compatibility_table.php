<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ribbon ↔ label compatibility is set by the Super Admin as one matrix
 * (ribbon type option × label material option), not ticked by each seller,
 * so a seller can't claim a wrong material by mistake.
 *
 * Also gives the three parameters the matrix depends on stable codes, and
 * removes the seller-filled "Compatible label material" ribbon parameter
 * together with its stored values (owner decision 2026-11-13: clear them).
 * That removal can't be undone by down().
 *
 * Parameters are located through data that already exists, never a
 * category name: the ribbon category owns the `ribbon_width` code, the
 * labels category owns the "Recommended ribbon type" parameter. Each step
 * is skipped when its target isn't found, so this is safe on an empty DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ribbon_label_compatibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ribbon_type_option_id')->constrained('category_parameter_options')->cascadeOnDelete();
            $table->foreignId('label_material_option_id')->constrained('category_parameter_options')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ribbon_type_option_id', 'label_material_option_id'], 'rlc_pair_unique');
            $table->index('label_material_option_id');
        });

        $ribbonCategoryId = DB::table('category_parameters')->where('code', 'ribbon_width')->value('category_id');
        $labelCategoryId = DB::table('category_parameters')
            ->where('name->en', 'Recommended ribbon type')
            ->value('category_id');

        if ($ribbonCategoryId) {
            $this->assignCode($ribbonCategoryId, 'Type', 'ribbon_type');

            $oldParameterIds = DB::table('category_parameters')
                ->where('category_id', $ribbonCategoryId)
                ->where('name->en', 'Compatible label material')
                ->pluck('id');

            if ($oldParameterIds->isNotEmpty()) {
                $valueIds = DB::table('product_parameter_values')->whereIn('category_parameter_id', $oldParameterIds)->pluck('id');
                DB::table('product_parameter_value_options')->whereIn('product_parameter_value_id', $valueIds)->delete();
                DB::table('product_parameter_values')->whereIn('id', $valueIds)->delete();
                DB::table('category_parameter_options')->whereIn('category_parameter_id', $oldParameterIds)->delete();
                DB::table('category_parameters')->whereIn('id', $oldParameterIds)->delete();
            }
        }

        if ($labelCategoryId) {
            $this->assignCode($labelCategoryId, 'Material', 'label_material');
            $this->assignCode($labelCategoryId, 'Width', 'label_width');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ribbon_label_compatibility');

        DB::table('category_parameters')
            ->whereIn('code', ['ribbon_type', 'label_material', 'label_width'])
            ->update(['code' => null]);
    }

    private function assignCode(int $categoryId, string $englishName, string $code): void
    {
        $taken = DB::table('category_parameters')->where('category_id', $categoryId)->where('code', $code)->exists();

        if ($taken) {
            return;
        }

        DB::table('category_parameters')
            ->where('category_id', $categoryId)
            ->where('name->en', $englishName)
            ->whereNull('code')
            ->limit(1)
            ->update(['code' => $code]);
    }
};
