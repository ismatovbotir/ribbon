{{-- Plain type label chip, visually distinct from the status badge. --}}
<span {{ $attributes->merge(['class' => 'inline-flex h-5 items-center rounded-xs bg-surface-sunken px-1.5 text-xs font-medium whitespace-nowrap text-text-secondary']) }}>
    {{ $slot }}
</span>
