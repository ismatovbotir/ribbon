@props(['name', 'stroke' => null])

{{--
    Ribbon icon (IBM Carbon base + custom domain icons on the same 32px grid).
    The registry, names and aliases live in App\Support\Icon; the spec is in
    .claude/docs/design/21-icon-system.md. Decorative only (aria-hidden):
    always pair the icon with visible text, or give the control an aria-label.

    Usage: <x-icon name="edit" class="size-5" />
--}}
{!! \App\Support\Icon::svg($name, (string) $attributes->merge(['class' => 'size-5 shrink-0'])) !!}
