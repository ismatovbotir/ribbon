@props(['full' => false, 'compact' => false])

{{-- Language links (?lang=), not buttons. Spec 18 section 3.6 + spec 20 10.2 (Inter, not mono).
     $compact = the navbar version: a globe with the current language, opening a short list. --}}
@if ($compact)
    <div
        x-data="{ open: false }"
        x-on:click.outside="open = false"
        x-on:keydown.escape.window="open = false"
        {{ $attributes->class(['relative']) }}
    >
        <button
            type="button"
            x-on:click="open = ! open"
            aria-expanded="false"
            x-bind:aria-expanded="open"
            aria-controls="navbar-language"
            aria-label="{{ __('storefront.nav.language') }}"
            title="{{ __('storefront.nav.language') }}"
            class="flex h-11 items-center gap-1.5 rounded-sm px-2 text-sm font-medium text-text-primary hover:bg-surface-hover"
        >
            <x-storefront.icon name="globe" class="size-5" />
            <span>{{ strtoupper(app()->getLocale()) }}</span>
        </button>
        <ul id="navbar-language" x-show="open" x-cloak class="elev-2 absolute top-full right-0 mt-1 w-36 rounded-sm border border-border-strong bg-surface py-1">
            @foreach (['uz' => "O'zbekcha", 'ru' => 'Русский', 'en' => 'English'] as $locale => $label)
                <li>
                    <a
                        href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}"
                        @if (app()->getLocale() === $locale) aria-current="true" @endif
                        class="flex h-11 items-center px-3 text-sm {{ app()->getLocale() === $locale ? 'bg-accent-50 font-semibold text-accent-800' : 'text-text-primary hover:bg-surface-hover' }}"
                    >{{ $label }}</a>
                </li>
            @endforeach
        </ul>
    </div>
@else
    <div {{ $attributes->class(['grid grid-cols-3 divide-x divide-border-input overflow-hidden rounded-sm border border-border-input', $full ? 'w-full' : 'inline-grid']) }}>
        @foreach (['uz' => 'UZ', 'ru' => 'RU', 'en' => 'EN'] as $locale => $label)
            <a
                href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}"
                @if (app()->getLocale() === $locale) aria-current="true" @endif
                class="flex min-w-11 items-center justify-center px-3 font-sans text-sm font-medium {{ $full ? 'h-11 w-full' : 'h-9 lg:h-10' }} {{ app()->getLocale() === $locale ? 'bg-accent-700 text-white' : 'text-text-secondary hover:bg-surface-hover' }}"
            >{{ $label }}</a>
        @endforeach
    </div>
@endif
