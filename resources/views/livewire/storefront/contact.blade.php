{{-- Contact page (spec 19 7.2 in the spec 20 language). No yellow element: the submit is cobalt primary. --}}
<div>
    <nav aria-label="{{ __('storefront.catalog.breadcrumb_label') }}" class="no-scrollbar -mx-4 flex items-center gap-1.5 overflow-x-auto px-4 text-sm whitespace-nowrap md:mx-0 md:px-0">
        <a href="{{ route('storefront.home') }}" wire:navigate class="hit text-text-secondary hover:text-text-primary hover:underline">{{ __('storefront.catalog.breadcrumb_home') }}</a>
        <span class="text-text-muted" aria-hidden="true">/</span>
        <span class="font-medium text-text-primary" aria-current="page">{{ $title }}</span>
    </nav>

    <h1 class="mt-3 text-xl font-semibold text-text-primary md:text-2xl">{{ $title }}</h1>
    @if ($lead !== '')
        <p class="mt-2 max-w-2xl text-base text-text-secondary">{{ $lead }}</p>
    @endif
    @if ($introBody !== '')
        <x-storefront.prose class="mt-4">{!! $introBody !!}</x-storefront.prose>
    @endif

    @php
        $hasDetails = count($rows) > 0 || $legalName || $legalInn || $officeAddress;
    @endphp
    <div class="mt-6 grid gap-8 {{ $hasDetails ? 'lg:grid-cols-[minmax(0,1fr)_minmax(0,28rem)] lg:items-start lg:gap-12' : '' }}">
        @if ($hasDetails)
            <section aria-labelledby="contact-details">
                <h2 id="contact-details" class="text-lg font-semibold text-text-primary md:text-xl">{{ __('storefront.contact.details') }}</h2>

                @if (count($rows) > 0)
                    <dl class="mt-3 overflow-hidden rounded-md border border-border-strong bg-surface">
                        @foreach ($rows as $r)
                            <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-4 border-b border-border px-4 py-3 last:border-b-0 sm:grid-cols-[9rem_minmax(0,1fr)]">
                                <dt class="text-sm text-text-secondary">{{ $r['label'] }}</dt>
                                <dd class="min-w-0 text-base break-words text-text-primary">
                                    @if ($r['href'])
                                        <a href="{{ $r['href'] }}" @if (in_array($r['type'], ['telegram', 'channel'], true)) target="_blank" rel="noopener" @endif class="inline-flex min-h-11 items-center text-accent-700 hover:underline {{ $r['type'] === 'phone' ? 'font-mono tabular-nums' : '' }}">{{ $r['value'] }}</a>
                                    @else
                                        <span class="whitespace-pre-line">{{ $r['value'] }}</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                {{-- Phone first on mobile: a call button above the legal block --}}
                @if ($phoneHref)
                    <x-button tag="a" variant="secondary" size="lg" href="tel:{{ $phoneHref }}" class="mt-4 w-full sm:w-auto md:hidden">
                        <x-storefront.icon name="phone" class="size-5" />
                        {{ __('storefront.product_detail.call') }} <span class="font-mono tabular-nums">{{ $adminPhone }}</span>
                    </x-button>
                @endif

                @if ($legalName || $legalInn)
                    <div class="mt-6 rounded-md border border-border bg-surface-band px-4 py-3 text-sm text-text-secondary">
                        @if ($legalName)<p class="font-semibold text-text-primary">{{ $legalName }}</p>@endif
                        @if ($legalInn)<p>{{ __('storefront.footer.inn') }} <span class="font-mono tabular-nums">{{ $legalInn }}</span></p>@endif
                        @if ($officeAddress)<p class="whitespace-pre-line">{{ $officeAddress }}</p>@endif
                    </div>
                @endif
            </section>
        @endif

        <section aria-labelledby="contact-form" class="rounded-md border border-border-strong bg-surface p-4 md:p-6 {{ $hasDetails ? '' : 'lg:max-w-xl' }}">
            <h2 id="contact-form" class="text-lg font-semibold text-text-primary">{{ __('storefront.contact.form_title') }}</h2>

            @if ($submitted)
                <x-alert variant="success" class="mt-4 mb-0">{{ __('storefront.contact.success', ['phone' => $phone]) }}</x-alert>
            @else
                <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.contact.form_hint') }}</p>

                <form wire:submit="submit" class="mt-4 space-y-4" novalidate>
                    {{-- Honeypot: hidden from humans and assistive tech, see Contact::$website --}}
                    <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
                        <label>Website <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <x-field :label="__('storefront.contact.phone')" for="contact-phone" :required="true" :hint="__('storefront.offer_request.phone_hint')" :error="$errors->first('phone')">
                        <x-input id="contact-phone" type="tel" inputmode="tel" autocomplete="tel" wire:model="phone" :error="$errors->has('phone')" placeholder="{{ __('storefront.offer_request.phone_placeholder') }}" class="font-mono tabular-nums" />
                    </x-field>

                    <x-field :label="__('storefront.contact.message')" for="contact-message" :error="$errors->first('message')">
                        <x-textarea id="contact-message" wire:model="message" rows="4" maxlength="1000" :error="$errors->has('message')" class="min-h-28" />
                    </x-field>

                    <x-button type="submit" variant="primary" size="lg" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="submit">{{ __('storefront.contact.send') }}</x-button>

                    <p class="text-xs text-text-muted">
                        {{ __('storefront.contact.consent') }}
                        @if (\App\Models\Page::query()->isPublished()->where('key', 'privacy')->exists())
                            <a href="{{ route('storefront.pages.privacy') }}" wire:navigate class="text-accent-700 underline underline-offset-2 hover:text-accent-800">{{ __('storefront.footer.privacy_link') }}</a>
                        @endif
                    </p>
                </form>
            @endif
        </section>
    </div>
</div>
