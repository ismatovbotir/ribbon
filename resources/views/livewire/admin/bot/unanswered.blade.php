<div>
    <x-page-header :title="__('admin.bot.title')" :subtitle="__('admin.bot.unanswered_hint')" />

    @include('livewire.admin.bot.tabs', ['current' => 'unanswered'])

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">{{ session('status') }}</div>
    @endif

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="relative overflow-x-auto">
            <table class="w-full min-w-[640px] border-collapse">
                <thead class="bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5">{{ __('admin.bot.question') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.bot.language') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ __('admin.bot.times') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ __('admin.bot.last_asked') }}</th>
                        <th class="px-4 py-2.5"><span class="sr-only">{{ __('admin.ui.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($questions as $question)
                        <tr wire:key="bot-q-{{ $question->id }}" class="h-row-comfortable text-sm text-text-primary hover:bg-surface-hover">
                            <td class="max-w-lg px-4 py-2 break-words">{{ $question->text }}</td>
                            <td class="px-4 py-2 font-mono text-xs text-text-secondary uppercase">{{ $question->locale }}</td>
                            <td class="px-4 py-2 text-right font-mono text-xs tabular-nums">{{ $question->times }}</td>
                            <td class="px-4 py-2 text-right text-xs text-text-secondary">{{ $question->last_asked_at->diffForHumans() }}</td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-button tag="a" href="{{ route('admin.bot.create', ['from' => $question->id]) }}" wire:navigate variant="primary" size="sm">{{ __('admin.bot.teach') }}</x-button>
                                    <x-button variant="ghost" size="sm" wire:click="dismiss({{ $question->id }})">{{ __('admin.bot.dismiss') }}</x-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center text-sm text-text-secondary">{{ __('admin.bot.unanswered_empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($questions->hasPages())
            <div class="border-t border-border px-4 py-3">{{ $questions->links() }}</div>
        @endif
    </div>
</div>
