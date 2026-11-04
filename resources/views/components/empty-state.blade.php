@props(['variant' => 'empty', 'title', 'description' => null])

<div class="flex flex-col items-center px-6 py-12 text-center md:py-16">
    <span class="mb-4 flex size-12 items-center justify-center rounded-md border border-border-strong bg-surface-subtle text-text-muted">
        <x-icon :name="['filtered' => 'funnel', 'forbidden' => 'lock', 'error' => 'warning-alt'][$variant] ?? 'product'" class="size-6" />
    </span>
    <h3 class="text-lg font-semibold text-text-primary">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-text-secondary">{{ $description }}</p>
    @endif
    @isset($actions)
        <div class="mt-5 flex w-full max-w-xs flex-col gap-2 md:w-auto md:max-w-none md:flex-row">{{ $actions }}</div>
    @endisset
</div>
