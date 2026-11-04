@if ($paginator->hasPages())
    @php
        $page = $paginator->currentPage();
        $last = $paginator->lastPage();
        $name = $paginator->getPageName();
        $window = collect([1, $last, $page - 1, $page, $page + 1])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
        $scroll = "\$nextTick(() => document.getElementById('list')?.scrollIntoView())";
        $btn = 'flex h-control min-w-control items-center justify-center rounded-sm border border-border-input bg-surface px-3 text-sm hover:bg-surface-hover';
        $seg = 'flex h-control min-w-control items-center justify-center bg-surface px-2 text-sm tabular-nums hover:bg-surface-hover';
    @endphp
    {{-- Real links (crawlers, no-JS) that Livewire upgrades to in-place page changes. --}}
    <nav aria-label="{{ __('storefront.pagination.label') }}" class="mt-6 border-t border-border-strong pt-4">
        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 md:hidden">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="{{ $btn }} text-text-muted">{{ __('storefront.pagination.prev') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" wire:click.prevent="previousPage('{{ $name }}')" x-on:click="{{ $scroll }}" class="{{ $btn }} text-text-primary">{{ __('storefront.pagination.prev') }}</a>
            @endif
            <span class="text-sm text-text-secondary tabular-nums">{{ $page }} / {{ $last }}</span>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" wire:click.prevent="nextPage('{{ $name }}')" x-on:click="{{ $scroll }}" class="{{ $btn }} text-text-primary">{{ __('storefront.pagination.next') }}</a>
            @else
                <span aria-disabled="true" class="{{ $btn }} text-text-muted">{{ __('storefront.pagination.next') }}</span>
            @endif
        </div>

        <ul class="hidden justify-center md:flex">
            <li class="inline-flex divide-x divide-border-input overflow-hidden rounded-sm border border-border-input">
                @php($prev = null)
                @foreach ($window as $p)
                    @if ($prev !== null && $p - $prev > 1)
                        <span class="{{ $seg }} text-text-muted">&hellip;</span>
                    @endif
                    <a
                        href="{{ $paginator->url($p) }}"
                        @if ($p === $page) aria-current="page" @endif
                        @if ($p === $page - 1) rel="prev" @elseif ($p === $page + 1) rel="next" @endif
                        wire:click.prevent="gotoPage({{ $p }}, '{{ $name }}')"
                        x-on:click="{{ $scroll }}"
                        aria-label="{{ __('storefront.pagination.page', ['n' => $p]) }}"
                        class="{{ $seg }} aria-[current=page]:bg-accent-700 aria-[current=page]:text-white"
                    >{{ $p }}</a>
                    @php($prev = $p)
                @endforeach
            </li>
        </ul>
    </nav>
@endif
