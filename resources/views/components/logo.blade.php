@props([
    // sm | md | lg: sets the font-size the whole lockup scales from (everything inside is in em).
    'size' => 'md',
    // default: graphite letters, cobalt-600 roll. reversed: white letters, cobalt-400 roll (dark backgrounds).
    'variant' => 'default',
    // Roll only, for collapsed or narrow places.
    'symbol' => false,
])

{{--
    Ribbon logo, concept D1 (.claude/docs/design/logo/concept-d.svg). "Ribb" + roll "o" + "n".
    The letters are live Manrope 800 text; the roll is an inline SVG sized in em.
    Roll geometry comes from the source artwork at 64px (outer r 18.2, centre 17.5 above the baseline), so at
    1em = 64px it is 36.4 x 36.4 units: 0.569em square, bottom 0.7 units (0.011em) below the baseline. No tail.
    Spacing around the o: margin-left / margin-right on the SVG below, tune by eye.
--}}
@php
    $reversed = $variant === 'reversed';
    $ring = $reversed ? '#60A5FA' : '#2563EB';
    $text = $reversed ? 'text-white' : 'text-graphite-900';
    $fontSize = $symbol
        ? ['sm' => 'text-4xl', 'md' => 'text-5xl', 'lg' => 'text-6xl'][$size] ?? 'text-5xl'
        : ['sm' => 'text-lg', 'md' => 'text-2xl', 'lg' => 'text-4xl'][$size] ?? 'text-2xl';
@endphp

<span
    role="img"
    aria-label="Ribbon"
    {{-- inline-block, not inline-flex: vertical-align on the SVG is ignored by flex items, which would misplace the roll against the baseline. --}}
    {{ $attributes->class(['inline-block whitespace-nowrap font-display font-extrabold leading-none select-none', $fontSize, $text]) }}
    style="letter-spacing: -0.0234em;"
>
    @unless ($symbol)<span aria-hidden="true">Ribb</span>@endunless<svg
        aria-hidden="true"
        focusable="false"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="-18.2 -35.7 36.4 36.4"
        class="inline-block shrink-0"
        style="width: 0.569em; height: 0.569em; vertical-align: -0.011em; @unless ($symbol)margin-left: 0.045em; margin-right: 0.02em;@endunless"
    >
        <path fill="{{ $ring }}" fill-rule="evenodd" d="M-18.2,-17.5a18.2,18.2 0 1,0 36.4,0a18.2,18.2 0 1,0 -36.4,0Z M-13.2,-17.5a13.2,13.2 0 1,0 26.4,0a13.2,13.2 0 1,0 -26.4,0Z M-12.2,-17.5a12.2,12.2 0 1,0 24.4,0a12.2,12.2 0 1,0 -24.4,0Z M-10.2,-17.5a10.2,10.2 0 1,0 20.4,0a10.2,10.2 0 1,0 -20.4,0Z"/>
        <path fill="#FACC15" fill-rule="evenodd" d="M-10.3,-17.5a10.3,10.3 0 1,0 20.6,0a10.3,10.3 0 1,0 -20.6,0Z M-7.4,-17.5a7.4,7.4 0 1,0 14.8,0a7.4,7.4 0 1,0 -14.8,0Z"/>
    </svg>@unless ($symbol)<span aria-hidden="true">n</span>@endunless
</span>
