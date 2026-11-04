@if ($paginator->hasPages())
    @php
        $page = $paginator->currentPage();
        $last = $paginator->lastPage();
        $name = $paginator->getPageName();
        $window = collect([1, $last, $page - 1, $page, $page + 1])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
    @endphp
    <nav aria-label="Pagination" class="flex flex-col gap-3 border-t border-border-strong p-3 md:flex-row md:items-center md:justify-between md:px-4">
        <p class="text-xs text-text-secondary">
            Showing <span class="font-mono">{{ $paginator->firstItem() }}-{{ $paginator->lastItem() }}</span>
            of <span class="font-mono">{{ $paginator->total() }}</span>
        </p>

        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 md:hidden">
            <x-button variant="secondary" wire:click="previousPage('{{ $name }}')" x-on:click="$root.closest('main')?.scrollTo(0, 0)" :disabled="$paginator->onFirstPage()">Prev</x-button>
            <span class="font-mono text-sm text-text-secondary">{{ $page }} / {{ $last }}</span>
            <x-button variant="secondary" wire:click="nextPage('{{ $name }}')" x-on:click="$root.closest('main')?.scrollTo(0, 0)" :disabled="! $paginator->hasMorePages()">Next</x-button>
        </div>

        <ul class="hidden md:flex">
            @php($prev = null)
            @foreach ($window as $p)
                @if ($prev !== null && $p - $prev > 1)
                    <li class="-ml-px"><span class="flex h-control min-w-control items-center justify-center border border-border-input bg-surface px-2 font-mono text-sm text-text-muted">…</span></li>
                @endif
                <li class="-ml-px first:ml-0">
                    <button type="button" wire:click="gotoPage({{ $p }}, '{{ $name }}')" x-on:click="$root.closest('main')?.scrollTo(0, 0)"
                        @if ($p === $page) aria-current="page" @endif aria-label="Page {{ $p }}"
                        class="flex h-control min-w-control items-center justify-center border border-border-input bg-surface px-2 font-mono text-sm hover:bg-surface-hover aria-[current=page]:relative aria-[current=page]:z-10 aria-[current=page]:border-accent-700 aria-[current=page]:bg-accent-700 aria-[current=page]:text-white">{{ $p }}</button>
                </li>
                @php($prev = $p)
            @endforeach
        </ul>
    </nav>
@endif
