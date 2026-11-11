<div>
    <x-page-header :title="__('admin.bot.title')" :subtitle="__('admin.bot.subtitle')">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.bot.guide') }}" wire:navigate variant="ghost">{{ __('admin.bot.tab_guide') }}</x-button>
            <x-button tag="a" href="{{ route('admin.bot.create') }}" wire:navigate variant="primary">
                <x-icon name="add" class="size-4" />
                {{ __('admin.bot.add') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @include('livewire.admin.bot.tabs', ['current' => 'index'])

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">{{ session('status') }}</div>
    @endif

    <section aria-labelledby="bot-switches" class="mb-6 rounded-md border border-border-strong bg-surface-raised">
        <div class="border-b border-border bg-surface-subtle px-5 py-3">
            <h2 id="bot-switches" class="text-lg font-semibold text-text-primary">{{ __('admin.bot.switches') }}</h2>
            <p class="text-xs text-text-muted">{{ __('admin.bot.switch_hint') }}</p>
        </div>
        @if ($canSwitch)
            <div class="grid gap-2 px-5 py-4 sm:grid-cols-2">
                <x-toggle wire:model.live="webEnabled" :label="__('admin.bot.switch_web')" :caption="$webEnabled ? __('admin.bot.on') : __('admin.bot.off')" />
                <x-toggle wire:model.live="telegramEnabled" :label="__('admin.bot.switch_telegram')" :caption="$telegramEnabled ? __('admin.bot.on') : __('admin.bot.off')" />
            </div>
        @else
            {{-- Moderators see the state; only Super Admin switches the bot on or off. --}}
            <dl class="grid gap-x-10 gap-y-2 px-5 py-4 sm:grid-cols-2">
                @foreach (['switch_web' => $webEnabled, 'switch_telegram' => $telegramEnabled] as $label => $on)
                    <div class="flex min-h-11 items-center justify-between gap-4">
                        <dt class="text-sm text-text-primary">{{ __('admin.bot.'.$label) }}</dt>
                        <dd><x-badge :variant="$on ? 'success' : 'muted'" dot>{{ $on ? __('admin.bot.on') : __('admin.bot.off') }}</x-badge></dd>
                    </div>
                @endforeach
            </dl>
            <p class="px-5 pb-4 text-xs text-text-muted">{{ __('admin.bot.switch_super_only') }}</p>
        @endif
    </section>

    @unless ($hasStart)
        <div class="mb-4 rounded-md border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">{{ __('admin.bot.no_start') }}</div>
    @endunless

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="relative overflow-x-auto">
            <table class="w-full min-w-[720px] border-collapse">
                <thead class="bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5">{{ __('admin.bot.name') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.bot.keywords') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.ui.status') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ __('admin.bot.hits') }}</th>
                        <th class="px-4 py-2.5"><span class="sr-only">{{ __('admin.ui.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($answers as $answer)
                        @php($keywords = collect(config('ribbon.locales'))->flatMap(fn ($l) => $answer->keywordsFor($l))->unique())
                        <tr wire:key="bot-answer-{{ $answer->id }}" class="h-row-comfortable text-sm text-text-primary hover:bg-surface-hover">
                            <td class="px-4 py-2 font-medium">
                                <a href="{{ route('admin.bot.edit', $answer) }}" wire:navigate class="hover:text-accent-700 hover:underline">{{ $answer->name }}</a>
                                @if ($answer->is_start)
                                    <x-badge variant="info" class="ml-2">{{ __('admin.bot.start') }}</x-badge>
                                @endif
                            </td>
                            <td class="max-w-md px-4 py-2 text-text-secondary">
                                <span class="line-clamp-2">{{ $keywords->take(8)->implode(', ') }}{{ $keywords->count() > 8 ? ' …' : '' }}</span>
                            </td>
                            <td class="px-4 py-2">
                                <x-badge :variant="$answer->is_active ? 'success' : 'muted'" dot>{{ $answer->is_active ? __('admin.bot.active') : __('admin.bot.inactive') }}</x-badge>
                            </td>
                            <td class="px-4 py-2 text-right font-mono text-xs text-text-secondary tabular-nums">{{ $answer->hits }}</td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-button tag="a" href="{{ route('admin.bot.edit', $answer) }}" wire:navigate variant="secondary" size="sm">{{ __('admin.ui.edit') }}</x-button>
                                    <x-button variant="danger" size="sm" wire:click="confirmDelete({{ $answer->id }})">{{ __('admin.ui.delete') }}</x-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center">
                                <p class="text-sm font-medium text-text-primary">{{ __('admin.bot.empty') }}</p>
                                <p class="mt-1 text-sm text-text-secondary">{{ __('admin.bot.empty_hint') }}</p>
                                <x-button tag="a" href="{{ route('admin.bot.create') }}" wire:navigate variant="primary" size="sm" class="mt-3">{{ __('admin.bot.add') }}</x-button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($this->deletingAnswer)
        <x-modal :title="__('admin.ui.delete_named', ['name' => $this->deletingAnswer->name])" close="cancelDelete">
            <p class="text-sm text-text-secondary">{{ __('admin.bot.delete_confirm') }}</p>
            <x-slot:footer>
                <x-button variant="ghost" wire:click="cancelDelete">{{ __('admin.ui.cancel') }}</x-button>
                <x-button variant="danger-solid" wire:click="deleteAnswer" wire:loading.attr="disabled" wire:target="deleteAnswer">{{ __('admin.ui.delete') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
