<div>
    <x-page-header :title="__('admin.ui.articles')" :subtitle="__('admin.ui.educational_content_about_ribbon_thermal_transfer_ribbons_an')">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.articles.create') }}" wire:navigate variant="primary">
                <x-icon name="add" class="size-4" />
                {{ __('admin.ui.add_article') }}
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
            <x-input type="search" wire:model.live.debounce.400ms="search" :aria-label="__('admin.ui.search_articles')" :placeholder="__('admin.ui.search_articles_2')" class="h-8" />
        </div>
        @if ($search !== '')
            <button type="button" wire:click="$set('search', '')" class="text-xs text-text-secondary hover:text-text-primary">{{ __('admin.ui.clear') }}</button>
        @endif

        <div class="flex items-center gap-1 rounded-sm border border-border p-0.5 text-xs sm:ml-auto">
            <button
                type="button"
                wire:click="filterByType('')"
                class="rounded-sm px-2.5 py-1 font-medium transition-colors {{ $type === '' ? 'bg-accent-50 text-accent-700' : 'text-text-muted hover:text-text-primary' }}"
            >
                {{ __('admin.ui.all') }}
            </button>
            @foreach (\App\Models\Article::TYPES as $option)
                <button
                    type="button"
                    wire:click="filterByType('{{ $option }}')"
                    class="rounded-sm px-2.5 py-1 font-medium capitalize transition-colors {{ $type === $option ? 'bg-accent-50 text-accent-700' : 'text-text-muted hover:text-text-primary' }}"
                >
                    {{ __('admin.ui.type_'.$option) }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="relative overflow-x-auto">
            <table class="w-full min-w-[760px] border-collapse">
                <thead class="sticky top-0 z-(--z-sticky) bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5">{{ __('admin.ui.cover') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.ui.title') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.ui.type') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.ui.status') }}</th>
                        <th class="px-4 py-2.5 text-right">
                            <button type="button" wire:click="sortBy('published_at')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                {{ __('admin.ui.published') }}
                                @if ($sortField === 'published_at')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5 text-right">
                            <button type="button" wire:click="sortBy('created_at')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                {{ __('admin.ui.created') }}
                                @if ($sortField === 'created_at')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5"><span class="sr-only">{{ __('admin.ui.actions') }}</span></th>
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
                                            alt="{{ $article->title[app()->getLocale()] ?? 'Article' }}"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        >
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-2 font-medium">
                                <a href="{{ route('admin.articles.edit', $article) }}" wire:navigate class="hover:text-accent-700 hover:underline">
                                    {{ $article->title[app()->getLocale()] ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-2 text-text-secondary ">{{ __('admin.ui.type_'.$article->type) }}</td>
                            <td class="px-4 py-2">
                                <x-badge :variant="$status['variant']" dot>{{ $status['label'] }}</x-badge>
                            </td>
                            <td class="px-4 py-2 text-right text-xs text-text-secondary">{{ $article->published_at?->translatedFormat('M j, Y') ?? '—' }}</td>
                            <td class="px-4 py-2 text-right text-xs text-text-secondary">{{ $article->created_at->translatedFormat('M j, Y') }}</td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-button tag="a" href="{{ route('admin.articles.edit', $article) }}" wire:navigate variant="secondary" size="sm">{{ __('admin.ui.edit') }}</x-button>
                                    <x-button variant="danger" size="sm" wire:click="confirmDelete({{ $article->id }})">{{ __('admin.ui.delete') }}</x-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                @if ($search !== '')
                                    <p class="text-sm font-medium text-text-primary">{{ __('admin.ui.no_results_match_your_filters') }}</p>
                                    <p class="mt-1 text-sm text-text-secondary">{{ __('admin.ui.try_a_different_search_term') }}</p>
                                    <button type="button" wire:click="$set('search', '')" class="mt-3 text-sm font-medium text-accent-700 hover:underline">{{ __('admin.ui.clear_filters') }}</button>
                                @else
                                    <p class="text-sm font-medium text-text-primary">{{ __('admin.ui.no_articles_yet') }}</p>
                                    <p class="mt-1 text-sm text-text-secondary">{{ __('admin.ui.write_the_first_article_history_ribbon_types_use_cases_techn') }}</p>
                                    <x-button tag="a" href="{{ route('admin.articles.create') }}" wire:navigate variant="primary" size="sm" class="mt-3">{{ __('admin.ui.add_article') }}</x-button>
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

    @if ($this->deletingArticle)
        <x-modal :title="__('admin.ui.delete_named', ['name' => $this->deletingArticle->title[app()->getLocale()] ?? ''])" close="cancelDelete">
            <p class="text-sm text-text-secondary">{{ __('admin.ui.it_is_removed_from_the_storefront_immediately_this_can_t_be') }}</p>
            <x-slot:footer>
                <x-button variant="ghost" wire:click="cancelDelete">{{ __('admin.ui.cancel') }}</x-button>
                <x-button variant="danger-solid" wire:click="deleteArticle" wire:loading.attr="disabled" wire:target="deleteArticle">{{ __('admin.ui.delete_article') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
