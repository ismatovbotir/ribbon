{{--
    Section-scoped locale switcher (docs/design/05-form-patterns.md).
    One tab strip governs every translatable field nested in the default
    slot — wrap each locale's field block in `x-show="locale === 'uz'"`
    (etc.) so switching tabs swaps the visible input in place rather than
    revealing/hiding side-by-side inputs.

    $incomplete: array of locale codes that should show the red completion
    dot (a governed field is empty for that locale).
--}}
@props(['locales' => null, 'incomplete' => []])

@php($locales = $locales ?? config('ribbon.locales'))

<div x-data="{ locale: '{{ $locales[0] }}' }">
    <div class="mb-4 flex items-center gap-4 border-b border-border" role="tablist">
        @foreach ($locales as $loc)
            <button
                type="button"
                role="tab"
                x-on:click="locale = '{{ $loc }}'"
                :aria-selected="locale === '{{ $loc }}'"
                class="relative -mb-px px-0.5 pb-2 text-sm font-medium text-text-secondary transition-colors"
                :class="locale === '{{ $loc }}' ? 'border-b-2 border-accent-600 text-accent-700' : 'border-b-2 border-transparent hover:text-text-primary'"
            >
                {{ strtoupper($loc) }}
                @if (in_array($loc, $incomplete, true))
                    <span class="absolute -top-0.5 -right-2 h-1.5 w-1.5 rounded-full bg-danger-600" title="Missing a value for this locale"></span>
                @endif
            </button>
        @endforeach
    </div>

    {{ $slot }}
</div>
