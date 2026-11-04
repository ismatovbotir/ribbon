@props(['title', 'subtitle' => null, 'backUrl' => null, 'backLabel' => null])

<header class="mb-4 border-b border-border pb-4 md:mb-6 md:flex md:items-end md:justify-between md:gap-6">
    <div class="min-w-0">
        @if ($backUrl)
            <a href="{{ $backUrl }}" wire:navigate class="-ml-2 mb-1 inline-flex h-11 items-center gap-1 px-2 text-sm font-medium text-accent-700 md:hidden">
                <x-icon name="chevron-left" class="size-4" />
                {{ $backLabel ?? 'Back' }}
            </a>
        @endif
        <h1 class="text-xl font-bold text-balance text-text-primary">{{ $title }}</h1>
        @isset($meta)
            <div class="mt-2 flex flex-wrap items-center gap-2">{{ $meta }}</div>
        @endisset
        @if ($subtitle)
            <p class="mt-1 line-clamp-2 text-sm text-text-secondary md:line-clamp-none">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="mt-3 flex flex-wrap gap-2 md:mt-0 md:shrink-0 [&>*]:flex-1 md:[&>*]:flex-none">
            {{ $actions }}
        </div>
    @endisset
</header>
