{{-- Plain type label chip — visually distinct from the status Badge component
     (see docs/design/06-category-parameter-builder.md) so a parameter's
     data-type is never mistaken for a moderation state. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-sm bg-surface-sunken px-1.5 py-0.5 text-xs font-medium text-text-secondary whitespace-nowrap']) }}>
    {{ $slot }}
</span>
