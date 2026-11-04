@props(['full' => false])

{{-- Language links (?lang=), not buttons. Spec 18 section 3.6 + spec 20 10.2 (Inter, not mono). --}}
<div {{ $attributes->class(['grid grid-cols-3 divide-x divide-border-input overflow-hidden rounded-sm border border-border-input', $full ? 'w-full' : 'inline-grid']) }}>
    @foreach (['uz' => 'UZ', 'ru' => 'RU', 'en' => 'EN'] as $locale => $label)
        <a
            href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}"
            @if (app()->getLocale() === $locale) aria-current="true" @endif
            class="flex min-w-11 items-center justify-center px-3 font-sans text-sm font-medium {{ $full ? 'h-11 w-full' : 'h-9 lg:h-10' }} {{ app()->getLocale() === $locale ? 'bg-accent-700 text-white' : 'text-text-secondary hover:bg-surface-hover' }}"
        >{{ $label }}</a>
    @endforeach
</div>
