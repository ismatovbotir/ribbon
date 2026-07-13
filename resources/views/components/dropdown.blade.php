@props(['align' => 'right'])

<div x-data="{ open: false }" x-on:click.outside="open = false" class="relative inline-block text-left">
    <div x-on:click="open = !open">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-cloak
        x-transition.origin.top.{{ $align }}
        x-on:click="open = false"
        class="absolute {{ $align === 'right' ? 'right-0' : 'left-0' }} z-dropdown mt-1 w-44 overflow-hidden rounded-lg border border-border bg-surface-overlay py-1 shadow-sm"
    >
        {{ $slot }}
    </div>
</div>
