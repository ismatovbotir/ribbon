<div>
    <x-page-header :title="__('admin.cms.pages.title')" :subtitle="__('admin.cms.pages.subtitle')">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.pages.create') }}" wire:navigate variant="primary">
                <x-icon name="add" class="size-4" />
                {{ __('admin.cms.pages.add') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-4 grid grid-cols-1 gap-3 sm:flex sm:flex-wrap sm:items-end">
        <div>
            <label for="pages-status" class="mb-1 block text-xs font-medium text-text-secondary">{{ __('admin.cms.pages.filter_status') }}</label>
            <x-select id="pages-status" wire:model.live="status" class="sm:w-44">
                <option value="">{{ __('admin.cms.pages.filter_all') }}</option>
                <option value="published">{{ __('admin.cms.pages.status_published') }}</option>
                <option value="draft">{{ __('admin.cms.pages.status_draft') }}</option>
            </x-select>
        </div>
        <div>
            <label for="pages-placement" class="mb-1 block text-xs font-medium text-text-secondary">{{ __('admin.cms.pages.filter_placement') }}</label>
            <x-select id="pages-placement" wire:model.live="placement" class="sm:w-52">
                <option value="">{{ __('admin.cms.pages.filter_all') }}</option>
                <option value="header">{{ __('admin.cms.pages.placement_header') }}</option>
                <option value="company">{{ __('admin.cms.pages.placement_company') }}</option>
                <option value="legal">{{ __('admin.cms.pages.placement_legal') }}</option>
            </x-select>
        </div>
    </div>

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] border-collapse">
                <thead class="bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5">{{ __('admin.cms.pages.col_title') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.cms.pages.col_url') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.cms.pages.col_status') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.cms.pages.col_placement') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ __('admin.cms.pages.col_updated') }}</th>
                        <th class="px-4 py-2.5"><span class="sr-only">{{ __('admin.cms.pages.col_actions') }}</span></th>
                    </tr>
                </thead>
                <tbody wire:loading.class="opacity-50" wire:target="status, placement, delete" class="divide-y divide-border">
                    @forelse ($pages as $page)
                        @php($editable = $page->isEditableBy($user))
                        <tr wire:key="page-{{ $page->id }}" class="h-row-comfortable text-sm text-text-primary hover:bg-surface-hover">
                            <td class="px-4 py-2 font-medium">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($editable)
                                        <a href="{{ route('admin.pages.edit', $page) }}" wire:navigate class="hover:text-accent-700 hover:underline">{{ $page->localized('title', $defaultLocale) ?: '—' }}</a>
                                    @else
                                        <span>{{ $page->localized('title', $defaultLocale) ?: '—' }}</span>
                                        <x-icon name="lock" class="size-4 text-text-muted" />
                                    @endif
                                    @if ($page->isSystem())
                                        <x-badge variant="info">{{ __('admin.cms.pages.badge_system') }}</x-badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-2 font-mono text-xs text-text-secondary">{{ $page->path($defaultLocale) ?? '—' }}</td>
                            <td class="px-4 py-2">
                                @if ($page->is_published)
                                    <x-badge variant="success" dot>{{ __('admin.cms.pages.status_published') }}</x-badge>
                                @else
                                    <x-badge variant="muted" dot>{{ __('admin.cms.pages.status_draft') }}</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex flex-wrap gap-1">
                                    @if ($page->show_in_header)
                                        <x-badge variant="muted">{{ __('admin.cms.pages.placement_header') }}</x-badge>
                                    @endif
                                    @if ($page->show_in_footer)
                                        <x-badge variant="muted">{{ $page->footer_column === 'legal' ? __('admin.cms.pages.placement_legal') : __('admin.cms.pages.placement_company') }}</x-badge>
                                    @endif
                                    @if (! $page->show_in_header && ! $page->show_in_footer)
                                        <span class="text-xs text-text-muted">{{ __('admin.cms.pages.placement_none') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-2 text-right text-xs text-text-secondary tabular-nums">{{ $page->updated_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($editable)
                                        <x-button tag="a" href="{{ route('admin.pages.edit', $page) }}" wire:navigate variant="secondary" size="sm">{{ __('admin.cms.pages.edit') }}</x-button>
                                    @else
                                        {{-- Shown with an explanation, not hidden, so staff know the page exists. --}}
                                        <x-button variant="secondary" size="sm" disabled aria-describedby="legal-lock-{{ $page->id }}">{{ __('admin.cms.pages.edit') }}</x-button>
                                        <span id="legal-lock-{{ $page->id }}" class="max-w-48 text-xs text-text-muted">{{ __('admin.cms.pages.legal_locked') }}</span>
                                    @endif
                                    @if ($editable && ! $page->isSystem())
                                        <x-button variant="danger" size="sm" wire:click="delete({{ $page->id }})" wire:confirm="{{ __('admin.cms.pages.delete_confirm') }}">{{ __('admin.cms.pages.delete') }}</x-button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <p class="text-sm font-medium text-text-primary">{{ __('admin.cms.pages.empty_title') }}</p>
                                <p class="mt-1 text-sm text-text-secondary">{{ __('admin.cms.pages.empty_body') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($pages->hasPages())
            <div class="border-t border-border px-4 py-3">
                {{ $pages->links() }}
            </div>
        @endif
    </div>
</div>
