@props(['title', 'subtitle' => null])

<div class="flex flex-col gap-4 pb-6 sm:flex-row sm:items-start sm:justify-between">
    <div>
        <h1 class="text-xl font-semibold text-text-primary">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-text-secondary">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
