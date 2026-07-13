{{--
    Shared dynamic parameter-value field, reused by both the Create and
    Edit (Specifications section) screens — renders one input per
    CategoryParameter.type, bound to `parameterValues.{parameterId}`.
    Caller wraps each field in its own `wire:key`.
--}}
@php
    $locale = app()->getLocale();
    $fallbackLocale = config('ribbon.locales')[0];
    $label = $parameter->name[$locale] ?? $parameter->name[$fallbackLocale] ?? '';
    $errorKey = 'parameterValues.'.$parameter->id;
@endphp

<div>
    <label class="mb-1 block text-sm font-medium text-text-primary">
        {{ $label }}
        @if ($parameter->is_required)
            <span class="text-danger-600">*</span>
        @endif
        @if ($parameter->type === 'number' && $parameter->unit)
            <span class="text-xs font-normal text-text-muted">({{ $parameter->unit }})</span>
        @endif
    </label>

    @if ($parameter->type === 'text')
        <x-input type="text" wire:model.blur="parameterValues.{{ $parameter->id }}" :error="$errors->has($errorKey)" />
    @elseif ($parameter->type === 'number')
        <x-input type="number" step="any" wire:model.blur="parameterValues.{{ $parameter->id }}" :error="$errors->has($errorKey)" />
    @elseif ($parameter->type === 'select_single')
        <x-select wire:model="parameterValues.{{ $parameter->id }}" :error="$errors->has($errorKey)">
            <option value="">{{ __('sellers.products.select_placeholder') }}</option>
            @foreach ($parameter->options as $option)
                <option value="{{ $option->id }}">{{ $option->value[$locale] ?? $option->value[$fallbackLocale] ?? '' }}</option>
            @endforeach
        </x-select>
    @elseif ($parameter->type === 'select_multiple')
        <div class="flex flex-wrap gap-x-4 gap-y-2 rounded-sm border border-border p-3">
            @foreach ($parameter->options as $option)
                <label class="flex items-center gap-2 text-sm text-text-primary">
                    <input
                        type="checkbox"
                        value="{{ $option->id }}"
                        wire:model="parameterValues.{{ $parameter->id }}"
                        class="h-4 w-4 rounded-xs border-border-strong accent-accent-600"
                    >
                    {{ $option->value[$locale] ?? $option->value[$fallbackLocale] ?? '' }}
                </label>
            @endforeach
        </div>
    @endif

    @error($errorKey)
        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
    @enderror
    @error($errorKey.'.*')
        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
    @enderror
</div>
