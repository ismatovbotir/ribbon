@props(['label', 'first' => false])

<p class="px-4 pb-1 text-xs font-semibold text-text-muted {{ $first ? 'pt-2' : 'pt-5' }}">{{ $label }}</p>
