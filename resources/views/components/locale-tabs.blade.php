{{--
    Section-scoped locale switcher (05-form-patterns, updated by 16 section 5.5).
    One 3-segment strip governs every translatable field nested in the default
    slot: wrap each locale's field block in
    `x-show="locale === 'uz'" role="tabpanel"` (etc.).
    $incomplete: locale codes that are missing a value (square marker + text line).
--}}
@props(['locales' => null, 'incomplete' => [], 'initial' => null])

@php($locales = $locales ?? config('ribbon.locales'))

<div x-data="{ locale: '{{ in_array($initial, $locales, true) ? $initial : $locales[0] }}', order: @js(array_values($locales)) }">
    <div role="tablist" aria-label="{{ __('admin.ui.language') }}"
         x-on:keydown.arrow-right.prevent="locale = order[(order.indexOf(locale) + 1) % order.length]; $nextTick(() => $refs['tab-' + locale].focus())"
         x-on:keydown.arrow-left.prevent="locale = order[(order.indexOf(locale) + order.length - 1) % order.length]; $nextTick(() => $refs['tab-' + locale].focus())"
         class="sticky top-0 z-(--z-bar) -mx-4 mb-3 grid grid-cols-3 divide-x divide-border border-y border-border-strong bg-surface md:static md:mx-0 md:inline-grid md:grid-cols-[repeat(3,5rem)] md:border">
        @foreach ($locales as $loc)
            <button
                type="button"
                role="tab"
                id="tab-{{ $loc }}"
                x-ref="tab-{{ $loc }}"
                aria-controls="panel-{{ $loc }}"
                :aria-selected="locale === '{{ $loc }}'"
                :tabindex="locale === '{{ $loc }}' ? 0 : -1"
                x-on:click="locale = '{{ $loc }}'"
                class="relative flex h-11 items-center justify-center gap-2 border-b-[3px] text-sm font-semibold dense:h-9"
                :class="locale === '{{ $loc }}' ? 'border-accent-700 bg-accent-50 text-accent-700' : 'border-transparent text-text-secondary hover:bg-surface-hover'"
            >
                {{ strtoupper($loc) }}
                @if (in_array($loc, $incomplete, true))
                    <span class="size-2 bg-danger-600"><span class="sr-only">{{ __('admin.ui.locale_missing') }}</span></span>
                @endif
            </button>
        @endforeach
    </div>
    @if ($incomplete)
        <p class="mb-3 text-xs font-medium text-danger-700">{{ __('admin.ui.locale_incomplete', ['locales' => strtoupper(implode(', ', $incomplete))]) }}</p>
    @endif

    {{ $slot }}
</div>
