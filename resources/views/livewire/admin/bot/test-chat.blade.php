<div class="mx-auto w-full max-w-3xl">
    <x-page-header :title="__('admin.bot.title')" :subtitle="__('admin.bot.test_hint')" />

    @include('livewire.admin.bot.tabs', ['current' => 'test'])

    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <label for="bot-test-locale" class="text-sm text-text-secondary">{{ __('admin.bot.language') }}</label>
            <x-select id="bot-test-locale" wire:model.live="locale" class="w-28">
                @foreach (config('ribbon.locales') as $code)
                    <option value="{{ $code }}">{{ strtoupper($code) }}</option>
                @endforeach
            </x-select>
        </div>
        <x-button type="button" variant="ghost" size="sm" wire:click="restart">{{ __('admin.bot.restart') }}</x-button>
    </div>

    <div class="rounded-md border border-border-strong bg-surface-raised">
        <ol
            class="flex max-h-[60vh] min-h-80 flex-col gap-3 overflow-y-auto p-4"
            aria-live="polite"
        >
            @foreach ($messages as $m => $message)
                @if ($message['from'] === 'user')
                    <li wire:key="msg-{{ $m }}" class="ml-auto max-w-[80%] rounded-md bg-accent-700 px-3 py-2 text-sm whitespace-pre-line text-white">{{ $message['text'] }}</li>
                @else
                    <li wire:key="msg-{{ $m }}" class="max-w-[85%]">
                        <div class="rounded-md border border-border bg-surface px-3 py-2 text-sm whitespace-pre-line text-text-primary">{{ $message['text'] }}</div>

                        @if (($message['form'] ?? null) !== null)
                            <p class="mt-1 text-xs text-text-muted italic">{{ __('admin.bot.form_note') }}</p>
                        @endif

                        @if (! empty($message['buttons']))
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($message['buttons'] as $b => $button)
                                    @if ($button['url'])
                                        <a href="{{ $button['url'] }}" target="_blank" rel="noopener" class="inline-flex min-h-9 items-center gap-1 rounded-sm border border-border-strong bg-surface px-2.5 text-xs font-medium text-accent-700 hover:bg-surface-hover">{{ $button['label'] }} <x-icon name="arrow-right" class="size-3" /></a>
                                    @else
                                        <button type="button" wire:click="press({{ $m }}, {{ $b }})" @disabled(! $loop->parent->last) class="inline-flex min-h-9 items-center rounded-sm border border-border-strong bg-surface px-2.5 text-xs font-medium text-text-primary hover:bg-surface-hover disabled:opacity-50">{{ $button['label'] }}</button>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        <p class="mt-1 font-mono text-[11px] text-text-muted">
                            {{ __('admin.bot.matched') }}:
                            {{ ($message['kind'] ?? '') === 'fallback' ? __('admin.bot.matched_none') : ($message['matchedBy'] ?? '—') }}
                        </p>
                    </li>
                @endif
            @endforeach
            {{-- New key per message count, so Alpine runs x-init again and the newest reply is scrolled into view. --}}
            <li wire:key="end-{{ count($messages) }}" x-data x-init="$el.parentElement.scrollTop = $el.parentElement.scrollHeight" aria-hidden="true"></li>
        </ol>

        <form wire:submit="send" class="flex gap-2 border-t border-border p-3">
            <label for="bot-test-text" class="sr-only">{{ __('admin.bot.test_placeholder') }}</label>
            <x-input id="bot-test-text" wire:model="text" maxlength="500" autocomplete="off" :placeholder="__('admin.bot.test_placeholder')" class="flex-1" />
            <x-button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="send">{{ __('admin.bot.send') }}</x-button>
        </form>
    </div>
</div>
