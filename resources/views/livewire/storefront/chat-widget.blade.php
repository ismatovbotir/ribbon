{{-- Logo button + window. Opening needs no server call (bot mode loads its greeting on first open). Above the phone bottom bar, below modals.
     Bot off: the message form. Bot on: the rule-based bot, whose "leave phone" / "talk to a person" open the same form. --}}
<div
    x-data="{ open: false }"
    x-on:keydown.escape.window="if (open) open = false"
    class="fixed right-4 bottom-20 z-(--z-bar) lg:right-6 lg:bottom-6"
>
    <div
        x-show="open"
        x-cloak
        x-trap.noautofocus="open"
        x-effect="if (open) setTimeout(() => { const list = $el.querySelector('ol'); if (list) list.scrollTop = list.scrollHeight; ($el.querySelector('#chat-text') || $el.querySelector('#chat-message'))?.focus() }, 50)"
        role="dialog"
        aria-modal="false"
        aria-labelledby="chat-title"
        class="elev-2 absolute right-0 bottom-full mb-3 flex max-h-[min(36rem,calc(100dvh-var(--spacing-storefront-header)-9.75rem))] lg:max-h-[min(36rem,calc(100dvh-var(--spacing-storefront-header)-6.25rem))] w-[calc(100vw-2rem)] max-w-sm flex-col rounded-md border border-border-strong bg-surface"
    >
        <div class="flex shrink-0 items-center justify-between gap-2 border-b border-border py-1 pr-1 pl-4">
            <h2 id="chat-title" class="font-display text-base font-semibold text-text-primary">{{ __('storefront.chat.title') }}</h2>
            <div class="flex items-center">
                @if ($botMode && count($messages) > 1)
                    <button type="button" wire:click="restartBot" class="flex h-11 items-center px-2 text-xs font-medium text-text-secondary hover:text-text-primary">{{ __('storefront.chat.restart') }}</button>
                @endif
                <button type="button" x-on:click="open = false" aria-label="{{ __('storefront.chat.close') }}" class="flex size-11 items-center justify-center text-text-secondary hover:text-text-primary">
                    <x-icon name="close" class="size-5" />
                </button>
            </div>
        </div>

        @if ($botMode)
            <ol class="flex min-h-24 flex-1 flex-col gap-3 overflow-y-auto overscroll-contain p-4" aria-live="polite">
                @if ($messages === [])
                    <li class="text-sm text-text-muted" wire:loading.delay>…</li>
                @endif
                @foreach ($messages as $m => $msg)
                    @if ($msg['from'] === 'user')
                        <li wire:key="chat-{{ $m }}" class="ml-auto max-w-[85%] rounded-md bg-accent-700 px-3 py-2 text-sm whitespace-pre-line text-white">{{ $msg['text'] }}</li>
                    @else
                        <li wire:key="chat-{{ $m }}" class="max-w-[90%]">
                            <div class="rounded-md border border-border bg-surface-band px-3 py-2 text-sm whitespace-pre-line text-text-primary">{{ $msg['text'] }}</div>
                            @if (! empty($msg['buttons']) && $loop->last && $formMode === null)
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($msg['buttons'] as $b => $button)
                                        @if (! empty($button['url']) && \App\Services\Bot\BotEngine::isExternal($button['url']))
                                            {{-- Another website: new tab, so the conversation stays open here. --}}
                                            <a href="{{ $button['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-1 rounded-sm border border-accent-700 bg-surface px-3 text-sm font-medium text-accent-700 hover:bg-accent-50">{{ $button['label'] }}<x-icon name="external-link" class="size-4" /><span class="sr-only">({{ __('storefront.opens_new_tab') }})</span></a>
                                        @elseif (! empty($button['url']))
                                            <a href="{{ $button['url'] }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 rounded-sm border border-accent-700 bg-surface px-3 text-sm font-medium text-accent-700 hover:bg-accent-50">{{ $button['label'] }}<x-icon name="arrow-right" class="size-4" /></a>
                                        @else
                                            <button type="button" wire:click="press({{ $m }}, {{ $b }})" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-sm border border-border-input bg-surface px-3 text-left text-sm font-medium text-text-primary hover:bg-surface-hover">{{ $button['label'] }}</button>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </li>
                    @endif
                @endforeach
                {{-- New key per message count, so Alpine runs x-init again and the newest message is scrolled into view. --}}
                <li wire:key="chat-end-{{ count($messages) }}-{{ $formMode ?? 'chat' }}" x-data x-init="$el.parentElement.scrollTop = $el.parentElement.scrollHeight; if (! document.activeElement || document.activeElement === document.body) document.getElementById('chat-text')?.focus({ preventScroll: true })" aria-hidden="true"></li>
            </ol>
        @endif

        <div class="shrink-0 {{ $botMode ? 'border-t border-border' : '' }} p-4">
            @if (! $botMode && $submitted)
                <p role="status" class="text-sm text-text-primary">{{ __('storefront.chat.success') }}</p>
            @elseif (! $botMode || $formMode !== null)
                @unless ($botMode)
                    <p class="mb-3 text-sm text-text-secondary">{{ __('storefront.chat.intro') }}</p>
                @endunless

                <form wire:submit="submit" class="space-y-3">
                    <div class="absolute -left-[9999px]" aria-hidden="true">
                        <label>Website <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <x-field :label="__('storefront.chat.message')" for="chat-message" :required="true" :error="$errors->first('message')">
                        <x-textarea id="chat-message" rows="3" maxlength="1000" wire:model="message" :error="$errors->has('message')" />
                    </x-field>

                    <x-field :label="__('storefront.chat.phone')" for="chat-phone" :required="true" :error="$errors->first('phone')">
                        <x-input id="chat-phone" type="tel" inputmode="tel" autocomplete="tel" wire:model="phone" :error="$errors->has('phone')" placeholder="+998 90 123 45 67" />
                    </x-field>

                    <x-button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="submit">{{ __('storefront.chat.send') }}</x-button>

                    @if ($botMode)
                        <button type="button" wire:click="backToBot" class="flex h-11 w-full items-center justify-center text-sm font-medium text-text-secondary hover:text-text-primary">{{ __('storefront.chat.back') }}</button>
                    @endif
                </form>
            @else
                <form wire:submit="ask" class="flex gap-2">
                    <label for="chat-text" class="sr-only">{{ __('storefront.chat.bot_placeholder') }}</label>
                    <x-input id="chat-text" wire:model="text" maxlength="500" autocomplete="off" :placeholder="__('storefront.chat.bot_placeholder')" class="min-w-0 flex-1" />
                    <x-button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="ask, press" aria-label="{{ __('storefront.chat.send') }}">
                        <x-icon name="paper-plane" class="size-4" />
                    </x-button>
                </form>
            @endif
        </div>
    </div>

    <button
        type="button"
        x-on:click="open = ! open{{ $botMode ? '; if (open && $wire.messages.length === 0) $wire.openBot()' : '' }}"
        x-bind:aria-expanded="open"
        aria-haspopup="dialog"
        aria-label="{{ __('storefront.chat.open') }}"
        class="elev-2 flex h-14 items-center gap-2 rounded-md border border-border-strong bg-surface px-3 hover:bg-surface-hover"
    >
        <x-logo :symbol="true" size="sm" aria-hidden="true" />
        <span class="hidden text-sm font-semibold text-text-primary md:inline">{{ __('storefront.chat.button') }}</span>
    </button>
</div>
