@props(['id' => null, 'title', 'href' => null, 'linkLabel' => null, 'center' => false])

{{-- H2 section heading (spec 20 section 5.3). Default: hairline rule, "view all" link on the right.
     $center: title on the page's central axis with the links under it (extra links go in the slot). --}}
@if ($center)
    <div {{ $attributes->class(['flex flex-col items-center gap-1 text-center']) }}>
        <h2 @if ($id) id="{{ $id }}" @endif class="scroll-mt-28 text-xl font-semibold text-text-primary md:text-2xl">{{ $title }}</h2>
        @if ($href || $slot->isNotEmpty())
            <div class="flex flex-wrap items-center justify-center gap-x-6">
                @if ($href)
                    <a href="{{ $href }}" wire:navigate class="inline-flex h-11 shrink-0 items-center gap-1 text-sm font-medium text-accent-700 hover:underline">
                        {{ $linkLabel }}<x-storefront.icon name="arrow-right" class="size-4" stroke="2" />
                    </a>
                @endif
                {{ $slot }}
            </div>
        @endif
    </div>
@else
    <div {{ $attributes->class(['flex items-end justify-between gap-4 border-b border-border pb-3']) }}>
        <h2 @if ($id) id="{{ $id }}" @endif class="text-lg font-semibold text-text-primary md:text-xl">{{ $title }}</h2>
        @if ($href)
            <a href="{{ $href }}" wire:navigate class="inline-flex h-11 shrink-0 items-center gap-1 text-sm font-medium text-accent-700 hover:underline">
                {{ $linkLabel }}<x-storefront.icon name="arrow-right" class="size-4" stroke="2" />
            </a>
        @endif
    </div>
@endif
