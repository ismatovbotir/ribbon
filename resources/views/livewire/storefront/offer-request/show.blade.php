<div class="mx-auto max-w-6xl">
    @if ($submitted)
        {{-- Submitted: no yellow button here; focus moves to the H1. --}}
        <section class="mx-auto max-w-xl rounded-md border border-border-strong p-6 text-center md:p-8" wire:key="request-submitted">
            <span class="mx-auto flex size-12 items-center justify-center rounded-md border border-accent-700 text-accent-700">
                <x-storefront.icon name="check" class="size-6" stroke="2" />
            </span>
            <h1 tabindex="-1" x-init="$el.focus()" class="mt-4 text-xl font-semibold text-text-primary md:text-2xl">{{ __('storefront.offer_request.confirmation_title') }}</h1>
            <p class="mt-2 text-sm text-text-secondary">{{ __('storefront.offer_request.confirmation_body') }}
                @if ($phone !== '')
                    <span class="font-mono whitespace-nowrap tabular-nums text-text-primary">{{ $phone }}</span>
                @endif
            </p>
            <ol class="mt-4 space-y-1 text-left text-sm text-text-secondary">
                @foreach ([1, 2, 3] as $step)
                    <li class="flex gap-3"><span class="text-text-muted tabular-nums">{{ $step }}.</span><span>{{ __('storefront.offer_request.next_step_'.$step) }}</span></li>
                @endforeach
            </ol>
            <x-button tag="a" href="{{ route('storefront.home') }}" wire:navigate variant="primary" class="mt-6">
                {{ __('storefront.offer_request.continue_shopping') }}
            </x-button>
        </section>
    @elseif (empty($groups))
        <section class="mx-auto max-w-xl rounded-md border border-border-strong">
            <h1 class="sr-only">{{ __('storefront.offer_request.title') }}</h1>
            <x-empty-state variant="empty" :title="__('storefront.offer_request.empty_title')" :description="__('storefront.offer_request.empty_body')">
                <x-slot:actions>
                    <x-button tag="a" href="{{ route('storefront.home') }}" wire:navigate variant="primary">{{ __('storefront.offer_request.browse_cta') }}</x-button>
                </x-slot:actions>
            </x-empty-state>
        </section>
    @else
        @php
            $itemCount = collect($groups)->sum(fn ($g) => count($g['lines']));
        @endphp
        <div class="lg:grid lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] lg:items-start lg:gap-10">
            <section aria-labelledby="req-title">
                <h1 id="req-title" class="text-xl font-semibold text-text-primary md:text-2xl">{{ __('storefront.offer_request.title') }}</h1>
                <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.offer_request.summary', ['items' => $itemCount, 'sellers' => count($groups)]) }}</p>

                @foreach ($groups as $group)
                    <section class="mt-4 overflow-hidden rounded-md border border-border-strong bg-surface" aria-label="{{ $group['seller']->name }}" wire:key="group-{{ $group['seller']->id }}">
                        <h2 class="flex min-h-11 items-center gap-2 border-b border-border bg-surface-band px-4 text-sm font-semibold text-text-primary">
                            <span class="truncate">{{ $group['seller']->name }}</span>
                            <x-storefront.verified />
                        </h2>

                        <ul>
                            @foreach ($group['lines'] as $line)
                                @php
                                    $product = $line['product'];
                                    $coverImage = $product->images->first();
                                    $productSlug = $product->slug[$locale] ?? ($product->slug[$defaultLocale] ?? $product->id);
                                    $productUrl = route('storefront.products.show', ['productSlug' => $productSlug]);
                                @endphp
                                <li class="grid grid-cols-[3.5rem_minmax(0,1fr)] gap-x-3 gap-y-2 border-b border-border px-4 py-3 last:border-b-0" wire:key="line-{{ $group['seller']->id }}-{{ $product->id }}-{{ $line['unit'] }}">
                                    <a href="{{ $productUrl }}" wire:navigate class="size-14 overflow-hidden rounded-sm border border-border bg-surface-subtle" tabindex="-1" aria-hidden="true">
                                        @if ($coverImage)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($coverImage->path) }}" alt="" width="56" height="56" class="size-full object-contain p-1" loading="lazy">
                                        @endif
                                    </a>

                                    <div class="min-w-0">
                                        <a href="{{ $productUrl }}" wire:navigate class="line-clamp-2 text-base leading-snug font-semibold text-text-primary hover:underline">{{ $line['displayName'] }}</a>
                                        <x-storefront.price-line :price="$line['unitPrice']" :unit="$line['unit']" :moq="$product->moqInPcs()" />
                                    </div>

                                    <div class="col-start-2 flex items-center justify-between gap-2">
                                        <x-storefront.qty-stepper
                                            :value="$line['qty']"
                                            :min="$line['minQty']"
                                            decrement="decrementLine({{ $group['seller']->id }}, '{{ $product->id }}', '{{ $line['unit'] }}')"
                                            increment="incrementLine({{ $group['seller']->id }}, '{{ $product->id }}', '{{ $line['unit'] }}')"
                                            :dec-label="__('storefront.offer_request.qty_decrease')"
                                            :inc-label="__('storefront.offer_request.qty_increase')"
                                        >
                                            <span class="flex h-11 w-16 items-center justify-center font-mono text-base text-text-primary tabular-nums">{{ $line['qty'] }}</span>
                                        </x-storefront.qty-stepper>
                                        <p class="font-mono text-sm text-text-secondary tabular-nums">~{{ number_format($line['lineTotal'], 0, '', "\u{00A0}") }}</p>
                                        <button
                                            type="button"
                                            wire:click="removeLine({{ $group['seller']->id }}, '{{ $product->id }}', '{{ $line['unit'] }}')"
                                            aria-label="{{ __('storefront.offer_request.remove_aria', ['name' => $line['displayName']]) }}"
                                            class="flex size-11 shrink-0 items-center justify-center text-text-muted hover:text-danger-700"
                                        >
                                            <x-storefront.icon name="close" class="size-5" />
                                        </button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        <div class="flex min-h-11 items-center justify-between border-t border-border px-4 text-sm">
                            <span class="text-text-secondary">{{ __('storefront.offer_request.subtotal_label') }}</span>
                            <span class="font-mono text-text-secondary tabular-nums">~{{ number_format($group['subtotal'], 0, '', "\u{00A0}") }} UZS</span>
                        </div>
                    </section>
                @endforeach

                <div class="mt-4 flex min-h-11 items-center justify-between rounded-md border border-border-strong px-4">
                    <span class="text-sm font-medium text-text-primary">{{ __('storefront.offer_request.grand_total_label') }}</span>
                    <span class="font-mono text-base font-medium text-text-primary tabular-nums">~{{ number_format($grandTotal, 0, '', "\u{00A0}") }} UZS</span>
                </div>
            </section>

            <section class="mt-8 lg:sticky lg:top-[calc(var(--spacing-storefront-header)+1rem)] lg:mt-0" aria-labelledby="req-contact-title">
                <div class="rounded-md border border-border-strong bg-surface p-4 md:p-6">
                    <h2 id="req-contact-title" class="text-lg font-semibold text-text-primary">{{ __('storefront.offer_request.contact_heading') }}</h2>
                    <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.offer_request.contact_subtitle') }}</p>

                    @error('phone')
                        @if ($message === __('storefront.offer_request.throttled') || $message === __('storefront.offer_request.empty_error'))
                            <x-alert variant="warning" class="mt-4 mb-0">{{ $message }}</x-alert>
                        @endif
                    @enderror

                    <form wire:submit="submit" class="mt-4 space-y-4" novalidate>
                        {{-- Honeypot: hidden from humans and assistive tech, see Show::$website --}}
                        <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
                            <label>Website <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
                        </div>

                        <x-field :label="__('storefront.offer_request.phone_label')" for="req-phone" :required="true" :hint="__('storefront.offer_request.phone_hint')" :error="$errors->first('phone')">
                            <x-input id="req-phone" type="tel" inputmode="tel" autocomplete="tel" wire:model.blur="phone" :error="$errors->has('phone')" placeholder="{{ __('storefront.offer_request.phone_placeholder') }}" class="h-14 font-mono text-lg" />
                        </x-field>

                        <details class="border-y border-border" @if ($companyName !== '' || $email !== '' || $buyerType !== '' || $errors->has('companyName') || $errors->has('email')) open @endif>
                            <summary class="group flex min-h-12 cursor-pointer items-center justify-between text-sm font-medium text-text-primary">
                                {{ __('storefront.offer_request.details_optional') }}
                                <x-storefront.icon name="chevron-down" class="size-4 text-text-muted group-open:rotate-180" stroke="2" />
                            </summary>
                            <div class="space-y-4 pb-4">
                                <x-field :label="__('storefront.offer_request.buyer_type_label')" for="req-buyer-type" :error="$errors->first('buyerType')">
                                    <x-select id="req-buyer-type" wire:model="buyerType" :error="$errors->has('buyerType')">
                                        <option value="">{{ __('storefront.offer_request.buyer_type_placeholder') }}</option>
                                        @foreach (\App\Models\OfferRequest::BUYER_TYPES as $type)
                                            <option value="{{ $type }}">{{ __('storefront.offer_request.buyer_type.'.$type) }}</option>
                                        @endforeach
                                    </x-select>
                                </x-field>
                                <x-field :label="__('storefront.offer_request.company_label')" for="req-company" :error="$errors->first('companyName')">
                                    <x-input id="req-company" type="text" autocomplete="organization" wire:model.blur="companyName" :error="$errors->has('companyName')" placeholder="{{ __('storefront.offer_request.company_placeholder') }}" />
                                </x-field>
                                <x-field :label="__('storefront.offer_request.email_label')" for="req-email" :error="$errors->first('email')">
                                    <x-input id="req-email" type="email" autocomplete="email" wire:model.blur="email" :error="$errors->has('email')" placeholder="{{ __('storefront.offer_request.email_placeholder') }}" />
                                </x-field>
                            </div>
                        </details>

                        <p class="text-xs text-text-muted">{{ __('storefront.offer_request.consent') }}</p>

                        <x-button type="submit" variant="cta" size="lg" class="w-full" wire:loading.attr="disabled" wire:target="submit">
                            {{ __('storefront.offer_request.submit_button') }}
                        </x-button>

                        <ul class="mt-4 space-y-2 text-sm text-text-secondary">
                            @foreach ([1, 2, 3] as $n)
                                <li class="flex gap-2"><x-storefront.icon name="check" class="mt-0.5 size-4 shrink-0 text-accent-700" stroke="2" /><span>{{ __('storefront.offer_request.assurance_'.$n) }}</span></li>
                            @endforeach
                        </ul>
                    </form>
                </div>
            </section>
        </div>
    @endif
</div>
