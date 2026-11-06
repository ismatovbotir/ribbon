{{-- Logo button + message window. Opening needs no server call. Above the phone bottom bar, below modals. --}}
<div x-data="{ open: false }" x-on:keydown.escape.window="if (open) open = false" class="fixed right-4 bottom-20 z-(--z-bar) lg:right-6 lg:bottom-6">
    <div
        x-show="open"
        x-cloak
        x-trap.noautofocus="open"
        x-effect="if (open) setTimeout(() => $el.querySelector('#chat-message')?.focus(), 50)"
        role="dialog"
        aria-modal="false"
        aria-labelledby="chat-title"
        class="elev-2 absolute right-0 bottom-full mb-3 w-[calc(100vw-2rem)] max-w-sm rounded-md border border-border-strong bg-surface"
    >
        <div class="flex items-center justify-between gap-2 border-b border-border py-1 pr-1 pl-4">
            <h2 id="chat-title" class="font-display text-base font-semibold text-text-primary">{{ __('storefront.chat.title') }}</h2>
            <button type="button" x-on:click="open = false" aria-label="{{ __('storefront.chat.close') }}" class="flex size-11 items-center justify-center text-text-secondary hover:text-text-primary">
                <x-icon name="close" class="size-5" />
            </button>
        </div>

        <div class="p-4">
            @if ($submitted)
                <p role="status" class="text-sm text-text-primary">{{ __('storefront.chat.success') }}</p>
            @else
                <p class="mb-3 text-sm text-text-secondary">{{ __('storefront.chat.intro') }}</p>

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
                </form>
            @endif
        </div>
    </div>

    <button
        type="button"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open"
        aria-haspopup="dialog"
        aria-label="{{ __('storefront.chat.open') }}"
        class="elev-2 flex h-14 items-center gap-2 rounded-md border border-border-strong bg-surface px-3 hover:bg-surface-hover"
    >
        <x-logo :symbol="true" size="sm" aria-hidden="true" />
        <span class="hidden text-sm font-semibold text-text-primary md:inline">{{ __('storefront.chat.button') }}</span>
    </button>
</div>
