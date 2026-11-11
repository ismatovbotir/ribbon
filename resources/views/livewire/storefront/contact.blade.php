{{-- Contact page (spec 19 7.2 in the spec 20 language). No yellow element: the submit is cobalt primary.
     Centred heading, then two equal columns: how to reach Ribbon (email, mobile phone, Telegram bot, other details,
     what happens next) | the form. One column on phones, contacts first. --}}
@php
    $channels = collect($rows)->whereIn('type', ['phone', 'email', 'telegram'])->sortBy(fn ($r) => array_search($r['type'], ['phone', 'email', 'telegram'], true))->values();
    $otherRows = collect($rows)->whereNotIn('type', ['phone', 'email', 'telegram'])->values();
    $channelIcons = ['phone' => 'phone', 'email' => 'email', 'telegram' => 'paper-plane'];
@endphp
<div>
    <header class="text-center">
        <nav aria-label="{{ __('storefront.catalog.breadcrumb_label') }}" class="no-scrollbar -mx-4 flex items-center justify-center gap-1.5 overflow-x-auto px-4 text-sm whitespace-nowrap md:mx-0 md:px-0">
            <a href="{{ route('storefront.home') }}" wire:navigate class="hit text-text-secondary hover:text-text-primary hover:underline">{{ __('storefront.catalog.breadcrumb_home') }}</a>
            <span class="text-text-muted" aria-hidden="true">/</span>
            <span class="font-medium text-text-primary" aria-current="page">{{ $title }}</span>
        </nav>

        <h1 class="mt-3 text-xl font-semibold text-text-primary md:text-2xl">{{ $title }}</h1>
        @if ($lead !== '')
            <p class="mx-auto mt-2 max-w-2xl text-base text-balance text-text-secondary">{{ $lead }}</p>
        @endif
    </header>

    @if ($introBody !== '')
        <x-storefront.prose class="mx-auto mt-4">{!! $introBody !!}</x-storefront.prose>
    @endif

    <div class="mt-8 grid gap-8 lg:grid-cols-2 lg:items-start">
        <section @if ($channels->isNotEmpty() || $otherRows->isNotEmpty()) aria-labelledby="contact-details" @else aria-labelledby="contact-next" @endif class="space-y-6">
            {{-- No phone, email or other details set yet: no empty heading, the steps stand alone. --}}
            @if ($channels->isNotEmpty() || $otherRows->isNotEmpty())
                <h2 id="contact-details" class="text-lg font-semibold text-text-primary md:text-xl">{{ __('storefront.contact.details') }}</h2>
            @endif

            @if ($channels->isNotEmpty())
                <ul class="space-y-3">
                    @foreach ($channels as $c)
                        <li>
                            <a
                                href="{{ $c['href'] }}"
                                @if ($c['type'] === 'telegram') target="_blank" rel="noopener" @endif
                                class="group flex items-center gap-4 rounded-md border border-border bg-surface p-4 hover:border-accent-700 hover:bg-surface-hover"
                            >
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-sm bg-accent-50 text-accent-700">
                                    <x-storefront.icon :name="$channelIcons[$c['type']]" class="size-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm text-text-secondary">{{ $c['label'] }}</span>
                                    <span class="block text-base font-semibold break-words text-text-primary group-hover:text-accent-700 {{ $c['type'] === 'phone' ? 'font-mono tabular-nums' : '' }}">{{ $c['value'] }}</span>
                                </span>
                                <x-storefront.icon name="arrow-right" class="ml-auto size-4 shrink-0 text-text-muted group-hover:text-accent-700" stroke="2" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($otherRows->isNotEmpty())
                <dl class="overflow-hidden rounded-md border border-border bg-surface">
                    @foreach ($otherRows as $r)
                        <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-4 border-b border-border px-4 py-3 last:border-b-0 sm:grid-cols-[9rem_minmax(0,1fr)]">
                            <dt class="text-sm text-text-secondary">{{ $r['label'] }}</dt>
                            <dd class="min-w-0 text-base break-words text-text-primary">
                                @if ($r['href'])
                                    <a href="{{ $r['href'] }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center text-accent-700 hover:underline">{{ $r['value'] }}</a>
                                @else
                                    <span class="whitespace-pre-line">{{ $r['value'] }}</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            <div class="rounded-md border border-border bg-surface-band p-4 md:p-6">
                <h3 id="contact-next" class="font-display text-base font-semibold text-text-primary">{{ __('storefront.contact.next_heading') }}</h3>
                <ol class="mt-3 space-y-3">
                    @foreach ([1, 2, 3] as $step)
                        <li class="flex items-start gap-3">
                            <span aria-hidden="true" class="flex size-7 shrink-0 items-center justify-center rounded-sm bg-accent-700 font-mono text-sm font-semibold text-white">{{ $step }}</span>
                            <span class="pt-0.5 text-sm text-text-secondary">{{ __('storefront.contact.next_'.$step) }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            @if ($legalName || $legalInn || $officeAddress)
                <div class="rounded-md border border-border px-4 py-3 text-sm text-text-secondary">
                    @if ($legalName)<p class="font-semibold text-text-primary">{{ $legalName }}</p>@endif
                    @if ($legalInn)<p>{{ __('storefront.footer.inn') }} <span class="font-mono tabular-nums">{{ $legalInn }}</span></p>@endif
                    @if ($officeAddress)<p class="whitespace-pre-line">{{ $officeAddress }}</p>@endif
                </div>
            @endif
        </section>

        <section aria-labelledby="contact-form" class="rounded-md border border-border-strong bg-surface p-4 md:p-6 lg:sticky lg:top-24">
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
                        <x-textarea id="contact-message" wire:model="message" rows="5" maxlength="1000" :error="$errors->has('message')" class="min-h-32" />
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
