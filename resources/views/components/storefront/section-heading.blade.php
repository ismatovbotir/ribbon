@props(['id' => null, 'title', 'href' => null, 'linkLabel' => null])

{{-- H2 section heading with a hairline rule and optional "view all" link (spec 20 section 5.3). --}}
<div {{ $attributes->class(['flex items-end justify-between gap-4 border-b border-border pb-3']) }}>
    <h2 @if ($id) id="{{ $id }}" @endif class="text-lg font-semibold text-text-primary md:text-xl">{{ $title }}</h2>
    @if ($href)
        <a href="{{ $href }}" wire:navigate class="inline-flex h-11 shrink-0 items-center gap-1 text-sm font-medium text-accent-700 hover:underline">
            {{ $linkLabel }}<x-storefront.icon name="arrow-right" class="size-4" stroke="2" />
        </a>
    @endif
</div>
