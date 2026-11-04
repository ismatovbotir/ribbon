{{-- Shared panel shell for admin and seller (spec 16 section 2, extended to seller).
     Must sit inside a <body class="panel-admin|panel-seller" x-data="{ mobileNavOpen: false }">.
     Props: tag (brand tag text), userName, userSub, breadcrumb, logoutLabel, openLabel, closeLabel, navLabel.
     Slots: nav (defined once, rendered in sidebar and drawer), sidebarFooter, topbarExtra, default. --}}
@props([
    'tag',
    'userName',
    'userSub' => null,
    'breadcrumb' => [],
    'logoutLabel' => 'Log out',
    'openLabel' => 'Open navigation',
    'closeLabel' => 'Close navigation',
    'skipLabel' => 'Skip to content',
])

@php
    $crumbs = array_values($breadcrumb);
    $lastCrumb = $crumbs ? end($crumbs) : null;
@endphp

<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-(--z-tooltip) focus:bg-surface focus:px-3 focus:py-2">{{ $skipLabel }}</a>

<div class="flex h-dvh overflow-hidden"
     x-data="{ mobileNavOpen: false }"
     x-on:keydown.escape.window="mobileNavOpen = false"
     x-on:livewire:navigated.window="mobileNavOpen = false">
    {{-- Sidebar (lg+) --}}
    <aside class="hidden w-sidebar shrink-0 border-r border-border-strong bg-surface-subtle lg:flex lg:flex-col">
        <div class="flex h-topbar shrink-0 items-center gap-2 border-b border-border-strong px-4">
            <x-logo size="sm" />
            <span class="inline-flex h-5 items-center bg-accent-700 px-1.5 text-xs font-semibold text-white">{{ $tag }}</span>
        </div>
        <nav class="flex-1 overflow-y-auto py-2" aria-label="{{ $tag }}">{{ $nav }}</nav>
        @isset($sidebarFooter)
            <div class="shrink-0 border-t border-border-strong px-4 py-3">{{ $sidebarFooter }}</div>
        @endisset
    </aside>

    {{-- Off-canvas drawer (<lg) --}}
    <div x-show="mobileNavOpen" x-cloak x-transition.opacity class="fixed inset-0 z-(--z-nav-backdrop) bg-scrim lg:hidden" x-on:click="mobileNavOpen = false"></div>
    <aside
        x-show="mobileNavOpen"
        x-cloak
        x-trap.noscroll.inert="mobileNavOpen"
        x-transition:enter="transition duration-200 ease-out motion-reduce:transition-none"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition duration-150 ease-in motion-reduce:transition-none"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        id="mobile-nav"
        role="dialog"
        aria-modal="true"
        aria-label="Navigation"
        class="fixed inset-y-0 left-0 z-(--z-nav) flex w-drawer flex-col border-r border-border-strong bg-surface-subtle pb-[env(safe-area-inset-bottom)] lg:hidden"
    >
        <div class="flex h-topbar shrink-0 items-center justify-between border-b border-border-strong pr-1 pl-4">
            <span class="flex items-center gap-2">
                <x-logo size="sm" />
                <span class="inline-flex h-5 items-center bg-accent-700 px-1.5 text-xs font-semibold text-white">{{ $tag }}</span>
            </span>
            <x-icon-button x-on:click="mobileNavOpen = false" aria-label="{{ $closeLabel }}">
                <x-icon name="close" class="size-5" />
            </x-icon-button>
        </div>
        <nav class="flex-1 overflow-y-auto py-2" aria-label="{{ $tag }}">{{ $nav }}</nav>
        <div class="shrink-0 border-t border-border-strong p-4 md:hidden">
            <p class="truncate text-sm font-medium text-text-primary">{{ $userName }}</p>
            @if ($userSub)
                <p class="truncate font-mono text-xs text-text-muted">{{ $userSub }}</p>
            @endif
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <x-button type="submit" variant="secondary" class="w-full">{{ $logoutLabel }}</x-button>
            </form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-(--z-sticky) flex h-topbar shrink-0 items-center gap-1 border-b border-border-strong bg-surface px-2 md:gap-3 md:px-6">
            <x-icon-button class="lg:hidden" x-on:click="mobileNavOpen = true" x-bind:aria-expanded="mobileNavOpen" aria-controls="mobile-nav" aria-label="{{ $openLabel }}" :dense="false">
                <x-icon name="menu" class="size-5" />
            </x-icon-button>

            <div class="min-w-0 flex-1">
                <p class="truncate font-display text-base font-semibold md:hidden">{{ $lastCrumb['label'] ?? '' }}</p>
                <nav class="hidden items-center gap-1.5 text-sm md:flex" aria-label="Breadcrumb">
                    @foreach ($crumbs as $crumb)
                        @if (! $loop->last)
                            @if (! empty($crumb['url']))
                                <a href="{{ $crumb['url'] }}" wire:navigate class="text-text-secondary hover:text-text-primary hover:underline">{{ $crumb['label'] }}</a>
                            @else
                                <span class="text-text-secondary">{{ $crumb['label'] }}</span>
                            @endif
                            <span class="text-text-muted">/</span>
                        @else
                            <span class="font-semibold text-text-primary" aria-current="page">{{ $crumb['label'] }}</span>
                        @endif
                    @endforeach
                </nav>
            </div>

            @isset($topbarExtra){{ $topbarExtra }}@endisset

            <div class="hidden md:block">
                <x-menu :title="$userName">
                    <x-slot:trigger>
                        <button type="button" aria-haspopup="menu" class="flex items-center gap-2 rounded-sm px-1.5 py-1 hover:bg-surface-hover">
                            <span class="flex size-8 items-center justify-center bg-accent-100 text-sm font-semibold text-accent-700">{{ \Illuminate\Support\Str::of($userName)->substr(0, 1)->upper() }}</span>
                            <span class="hidden text-left sm:block">
                                <span class="block text-sm font-medium text-text-primary">{{ $userName }}</span>
                                @if ($userSub)<span class="block font-mono text-xs text-text-muted">{{ $userSub }}</span>@endif
                            </span>
                        </button>
                    </x-slot:trigger>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" role="menuitem" class="flex min-h-12 w-full items-center gap-3 px-4 text-left text-sm text-text-primary hover:bg-surface-hover focus-visible:bg-surface-hover dense:min-h-9 dense:px-3"><x-icon name="logout" class="size-5 shrink-0 text-text-muted" /><span>{{ $logoutLabel }}</span></button>
                    </form>
                </x-menu>
            </div>
        </header>

        {{-- `relative` keeps absolutely positioned sr-only text inside this scroller,
             so it can't stretch the document and scroll the shell away (QA N4-05, N4-06).
             tabindex: the fallback for focus after an action removes the focused control. --}}
        <main id="main" tabindex="-1" class="relative flex-1 overflow-y-auto overscroll-contain outline-none">
            <div class="px-4 py-4 md:px-6 md:py-6">
                {{ $slot }}
            </div>
        </main>
    </div>
</div>
