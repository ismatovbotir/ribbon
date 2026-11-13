{{-- Ribbon type × label material matrix. See Livewire\Admin\Compatibility\Show. --}}
@php
    $locale = app()->getLocale();
    $fallback = config('ribbon.locales')[0];
    $label = fn ($option) => $option->value[$locale] ?? ($option->value[$fallback] ?? '');
@endphp

<div class="mx-auto w-full max-w-4xl">
    <x-page-header :title="__('admin.compatibility.title')" :subtitle="__('admin.compatibility.subtitle')">
        @if ($typeParameter && $materialParameter)
            <x-slot:actions>
                <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                    {{ __('admin.compatibility.save') }}
                </x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700" role="status">
            {{ session('status') }}
        </div>
    @endif

    @if (! $typeParameter || ! $materialParameter)
        <x-empty-state :title="__('admin.compatibility.missing_title')" :description="__('admin.compatibility.missing_body')" />
    @else
        <x-section :title="__('admin.compatibility.matrix_heading')" flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[32rem] text-sm">
                    <caption class="sr-only">{{ __('admin.compatibility.matrix_heading') }}</caption>
                    <thead>
                        <tr class="border-b border-border bg-surface-subtle">
                            <th scope="col" class="px-4 py-3 text-left font-medium text-text-secondary">{{ __('admin.compatibility.ribbon_type') }}</th>
                            @foreach ($materialParameter->options as $material)
                                <th scope="col" class="px-3 py-3 text-center font-medium text-text-secondary">{{ $label($material) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($typeParameter->options as $type)
                            <tr class="border-b border-border last:border-b-0" wire:key="type-{{ $type->id }}">
                                <th scope="row" class="px-4 py-2 text-left font-semibold text-text-primary">{{ $label($type) }}</th>
                                @foreach ($materialParameter->options as $material)
                                    <td class="px-3 py-1 text-center">
                                        <div class="inline-flex justify-center">
                                            <x-checkbox
                                                wire:model="pairs"
                                                value="{{ $type->id }}:{{ $material->id }}"
                                                :label="$label($type).' + '.$label($material)"
                                                :sr-only="true" />
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-section>
        <p class="mt-3 text-xs text-text-muted">{{ __('admin.compatibility.width_note') }}</p>
    @endif
</div>
