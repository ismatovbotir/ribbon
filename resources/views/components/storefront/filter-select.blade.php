@props(['parameter', 'facetCounts' => [], 'selectedOptionIds' => [], 'live' => true])

@php
    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];
@endphp

<div class="mb-4 border-b border-border pb-4 last:mb-0 last:border-b-0 last:pb-0">
    <p class="mb-2 text-sm font-semibold text-text-primary">
        {{ $parameter->name[$locale] ?? ($parameter->name[$defaultLocale] ?? '') }}
    </p>
    <div class="flex flex-col gap-1.5">
        @foreach ($parameter->options as $option)
            @php
                $count = $facetCounts[$option->id] ?? 0;
                $isChecked = in_array((string) $option->id, array_map('strval', $selectedOptionIds), true);
                $isDisabled = $count === 0 && ! $isChecked;
            @endphp
            <label class="flex items-center gap-2 text-sm {{ $isDisabled ? 'cursor-not-allowed text-text-muted' : 'cursor-pointer text-text-secondary hover:text-text-primary' }}">
                <input
                    type="checkbox"
                    value="{{ $option->id }}"
                    @if ($live) wire:model.live @else wire:model @endif="selected.{{ $parameter->id }}"
                    @disabled($isDisabled)
                    class="h-3.5 w-3.5 shrink-0 rounded-xs border-border text-accent-600 focus:ring-2 focus:ring-accent-100 disabled:cursor-not-allowed"
                >
                <span>{{ $option->value[$locale] ?? ($option->value[$defaultLocale] ?? '') }} ({{ $count }})</span>
            </label>
        @endforeach
    </div>
</div>
