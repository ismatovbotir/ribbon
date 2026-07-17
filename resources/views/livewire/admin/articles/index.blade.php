<div>
    <x-page-header title="Articles" subtitle="Educational content about Ribbon, thermal transfer ribbons, and the auto-ID industry — shown on the storefront's Articles section.">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.articles.create') }}" wire:navigate variant="primary">
                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                Add Article
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="w-full max-w-xs">
            <x-input type="search" wire:model.live.debounce.400ms="search" placeholder="Search articles…" class="h-8" />
        </div>
        @if ($search !== '')
            <button type="button" wire:click="$set('search', '')" class="text-xs text-text-secondary hover:text-text-primary">Clear</button>
        @endif

        <div class="flex items-center gap-1 rounded-sm border border-border p-0.5 text-xs sm:ml-auto">
            <button
                type="button"
                wire:click="filterByType('')"
                class="rounded-sm px-2.5 py-1 font-medium transition-colors {{ $type === '' ? 'bg-accent-50 text-accent-700' : 'text-text-muted hover:text-text-primary' }}"
            >
                All
            </button>
            @foreach (\App\Models\Article::TYPES as $option)
                <button
                    type="button"
                    wire:click="filterByType('{{ $option }}')"
                    class="rounded-sm px-2.5 py-1 font-medium capitalize transition-colors {{ $type === $option ? 'bg-accent-50 text-accent-700' : 'text-text-muted hover:text-text-primary' }}"
                >
                    {{ ucfirst($option) }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] border-collapse">
                <thead class="sticky top-0 z-sticky bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5">Cover</th>
                        <th class="px-4 py-2.5">Title</th>
                        <th class="px-4 py-2.5">Type</th>
                        <th class="px-4 py-2.5">Status</th>
                        <th class="px-4 py-2.5 text-right">
                            <button type="button" wire:click="sortBy('published_at')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                Published
                                @if ($sortField === 'published_at')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5 text-right">
                            <button type="button" wire:click="sortBy('created_at')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                Created
                                @if ($sortField === 'created_at')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody wire:loading.class="opacity-50" wire:target="search, sortBy, filterByType" class="divide-y divide-border">
                    @forelse ($articles as $article)
                        @php($status = \App\Livewire\Admin\Articles\Index::statusMeta($article))
                        <tr wire:key="article-{{ $article->id }}" class="h-row-comfortable text-sm text-text-primary hover:bg-surface-hover">
                            <td class="px-4 py-2">
                                <div class="h-10 w-16 overflow-hidden rounded-sm border border-border bg-surface-sunken">
                                    @if ($article->cover_image_path)
                                        <img
                                            src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($article->cover_image_path) }}"
                                            alt="{{ $article->title[$defaultLocale] ?? 'Article' }}"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        >
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-2 font-medium">
                                <a href="{{ route('admin.articles.edit', $article) }}" wire:navigate class="hover:text-accent-700 hover:underline">
                                    {{ $article->title[$defaultLocale] ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-2 text-text-secondary capitalize">{{ $article->type }}</td>
                            <td class="px-4 py-2">
                                <x-badge :variant="$status['variant']" dot>{{ $status['label'] }}</x-badge>
                            </td>
                            <td class="px-4 py-2 text-right text-xs text-text-secondary">{{ $article->published_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-4 py-2 text-right text-xs text-text-secondary">{{ $article->created_at->format('M j, Y') }}</td>
                            <td class="px-4 py-2 text-right">
                                <x-button tag="a" href="{{ route('admin.articles.edit', $article) }}" wire:navigate variant="secondary" size="sm">
                                    Edit
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                @if ($search !== '')
                                    <p class="text-sm font-medium text-text-primary">No results match your filters</p>
                                    <p class="mt-1 text-sm text-text-secondary">Try a different search term.</p>
                                    <button type="button" wire:click="$set('search', '')" class="mt-3 text-sm font-medium text-accent-700 hover:underline">Clear filters</button>
                                @else
                                    <p class="text-sm font-medium text-text-primary">No articles yet</p>
                                    <p class="mt-1 text-sm text-text-secondary">Write the first article — history, ribbon types, use cases, technical explainers.</p>
                                    <x-button tag="a" href="{{ route('admin.articles.create') }}" wire:navigate variant="primary" size="sm" class="mt-3">Add Article</x-button>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($articles->hasPages())
            <div class="border-t border-border px-4 py-3">
                {{ $articles->links() }}
            </div>
        @endif
    </div>
</div>
