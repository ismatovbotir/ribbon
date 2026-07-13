<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
    @if ($submitted)
        <div class="rounded-xl border border-border bg-surface-raised px-6 py-10 text-center">
            <h1 class="text-xl font-semibold text-text-primary">{{ __('storefront.offer_request.confirmation_title') }}</h1>
            <p class="mt-2 text-sm text-text-secondary">{{ __('storefront.offer_request.confirmation_body') }}</p>
            <x-button tag="a" href="{{ route('storefront.home') }}" wire:navigate variant="primary" class="mt-6">
                {{ __('storefront.offer_request.continue_shopping') }}
            </x-button>
        </div>
    @else
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-text-primary">{{ __('storefront.offer_request.title') }}</h1>
            <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.offer_request.subtitle') }}</p>
        </div>

        @if (empty($groups))
            <div class="rounded-xl border border-border bg-surface-raised px-6 py-10 text-center">
                <p class="text-sm font-medium text-text-primary">{{ __('storefront.offer_request.empty_title') }}</p>
                <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.offer_request.empty_body') }}</p>
                <x-button tag="a" href="{{ route('storefront.home') }}" variant="primary" class="mt-6">
                    {{ __('storefront.offer_request.browse_cta') }}
                </x-button>
            </div>
        @else
            <div class="space-y-6">
                @foreach ($groups as $group)
                    <div class="overflow-hidden rounded-xl border border-border bg-surface-raised">
                        <div class="border-b border-border bg-surface-subtle px-4 py-2.5">
                            <p class="text-sm font-semibold text-text-primary">{{ $group['seller']->name }}</p>
                        </div>

                        <ul class="divide-y divide-border">
                            @foreach ($group['lines'] as $line)
                                @php
                                    $product = $line['product'];
                                    $coverImage = $product->images->first();
                                    $productSlug = $product->slug[$locale] ?? ($product->slug[$defaultLocale] ?? $product->id);
                                @endphp
                                <li class="flex items-center gap-3 px-4 py-3" wire:key="line-{{ $group['seller']->id }}-{{ $product->id }}-{{ $line['unit'] }}">
                                    <a href="{{ route('storefront.products.show', ['productSlug' => $productSlug]) }}" wire:navigate class="h-14 w-14 shrink-0 overflow-hidden rounded-sm border border-border bg-surface-subtle">
                                        @if ($coverImage)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($coverImage->path) }}" alt="{{ $line['displayName'] }}" class="h-full w-full object-contain" loading="lazy">
                                        @endif
                                    </a>

                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('storefront.products.show', ['productSlug' => $productSlug]) }}" wire:navigate class="block truncate text-sm font-medium text-text-primary hover:text-accent-700">
                                            {{ $line['displayName'] }}
                                        </a>
                                        <p class="mt-0.5 text-xs text-text-muted">
                                            {{ number_format($line['unitPrice']) }} UZS / {{ __('storefront.unit.'.$line['unit']) }}
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <button
                                            type="button"
                                            wire:click="decrementLine({{ $group['seller']->id }}, '{{ $product->id }}', '{{ $line['unit'] }}')"
                                            aria-label="{{ __('storefront.offer_request.qty_decrease') }}"
                                            class="flex h-7 w-7 items-center justify-center rounded-sm border border-border text-text-secondary hover:bg-surface-hover"
                                        >−</button>
                                        <span class="w-8 text-center text-sm tabular-nums text-text-primary">{{ $line['qty'] }}</span>
                                        <button
                                            type="button"
                                            wire:click="incrementLine({{ $group['seller']->id }}, '{{ $product->id }}', '{{ $line['unit'] }}')"
                                            aria-label="{{ __('storefront.offer_request.qty_increase') }}"
                                            class="flex h-7 w-7 items-center justify-center rounded-sm border border-border text-text-secondary hover:bg-surface-hover"
                                        >+</button>
                                    </div>

                                    <p class="w-24 shrink-0 text-right text-sm font-semibold tabular-nums text-text-primary">
                                        {{ number_format($line['lineTotal']) }} UZS
                                    </p>

                                    <button
                                        type="button"
                                        wire:click="removeLine({{ $group['seller']->id }}, '{{ $product->id }}', '{{ $line['unit'] }}')"
                                        title="{{ __('storefront.offer_request.remove_button') }}"
                                        class="shrink-0 text-text-muted hover:text-danger-600"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                            <path d="M4 5h12M8 5V4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1m2 0-.6 10.2a1.5 1.5 0 0 1-1.5 1.4H7.1a1.5 1.5 0 0 1-1.5-1.4L5 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        <div class="flex items-center justify-between border-t border-border px-4 py-2.5">
                            <span class="text-xs text-text-secondary">{{ __('storefront.offer_request.subtotal_label') }}</span>
                            <span class="text-sm font-semibold tabular-nums text-text-primary">{{ number_format($group['subtotal']) }} UZS</span>
                        </div>
                    </div>
                @endforeach

                <div class="flex items-center justify-between rounded-xl border border-border bg-surface-raised px-4 py-3">
                    <span class="text-sm font-medium text-text-primary">{{ __('storefront.offer_request.grand_total_label') }}</span>
                    <span class="text-lg font-bold tabular-nums text-text-primary">{{ number_format($grandTotal) }} UZS</span>
                </div>
            </div>

            <div class="mt-8 rounded-xl border border-border bg-surface-raised px-5 py-5">
                <h2 class="text-base font-semibold text-text-primary">{{ __('storefront.offer_request.contact_heading') }}</h2>
                <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.offer_request.contact_subtitle') }}</p>

                <form wire:submit="submit" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-primary">
                            {{ __('storefront.offer_request.phone_label') }} <span class="text-danger-600">*</span>
                        </label>
                        <x-input type="tel" wire:model.blur="phone" :error="$errors->has('phone')" placeholder="{{ __('storefront.offer_request.phone_placeholder') }}" />
                        @error('phone')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-text-primary">{{ __('storefront.offer_request.company_label') }}</label>
                            <x-input type="text" wire:model.blur="companyName" :error="$errors->has('companyName')" placeholder="{{ __('storefront.offer_request.company_placeholder') }}" />
                            @error('companyName')
                                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-text-primary">{{ __('storefront.offer_request.email_label') }}</label>
                            <x-input type="email" wire:model.blur="email" :error="$errors->has('email')" placeholder="{{ __('storefront.offer_request.email_placeholder') }}" />
                            @error('email')
                                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <x-button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="submit">
                            {{ __('storefront.offer_request.submit_button') }}
                        </x-button>
                    </div>
                </form>
            </div>
        @endif
    @endif
</div>
