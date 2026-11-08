@props(['banners', 'locale', 'defaultLocale', 'aspect' => 'aspect-[16/9] md:aspect-[4/1]'])

{{-- Banner block (spec 18 section 4): hairline frame, caption in a solid band BELOW the image (no gradient, no text over image). --}}
@php $disk = \Illuminate\Support\Facades\Storage::disk('public'); @endphp

@if ($banners->count() === 1)
    @php $banner = $banners->first(); $bannerTitle = $banner->title[$locale] ?? ($banner->title[$defaultLocale] ?? ''); @endphp
    <x-storefront.banner-frame :href="$banner->link_url" class="block overflow-hidden rounded-lg border border-border bg-surface-subtle hover:border-accent-700">
        <picture>
            @if ($banner->mobile_image_path)
                <source media="(max-width: 767px)" srcset="{{ $disk->url($banner->mobile_image_path) }}">
            @endif
            <img src="{{ $disk->url($banner->image_path) }}" alt="{{ $bannerTitle }}" class="{{ $aspect }} w-full object-cover" loading="eager">
        </picture>
        @if ($bannerTitle !== '')
            <p class="border-t border-border bg-surface-band px-3 py-2 text-sm font-semibold text-text-primary">{{ $bannerTitle }}</p>
        @endif
    </x-storefront.banner-frame>
@else
    <div
        x-data="{
            current: 0,
            total: {{ $banners->count() }},
            timer: null,
            reduceMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            init() { if (! this.reduceMotion) this.play(); },
            play() { this.stop(); this.timer = setInterval(() => this.next(), 5500); },
            stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },
            next() { this.current = (this.current + 1) % this.total; },
            prev() { this.current = (this.current - 1 + this.total) % this.total; },
        }"
        x-on:mouseenter="stop()"
        x-on:mouseleave="if (! reduceMotion) play()"
        x-on:focusin="stop()"
        x-on:focusout="if (! reduceMotion) play()"
        x-on:keydown.left="prev()"
        x-on:keydown.right="next()"
        role="region"
        aria-roledescription="carousel"
        aria-label="{{ __('storefront.home.hero_carousel_label') }}"
        class="overflow-hidden rounded-lg border border-border bg-surface-subtle"
    >
        {{-- All slides stay in the DOM (crawlable); off-screen ones are aria-hidden and out of the tab order. --}}
        <div class="flex transition-transform duration-500 ease-out motion-reduce:transition-none" :style="`transform: translateX(-${current * 100}%)`">
            @foreach ($banners as $banner)
                @php $bannerTitle = $banner->title[$locale] ?? ($banner->title[$defaultLocale] ?? ''); @endphp
                <div class="w-full shrink-0" :aria-hidden="current !== {{ $loop->index }} ? 'true' : 'false'">
                    <x-storefront.banner-frame :href="$banner->link_url" class="block" x-bind:tabindex="current === {{ $loop->index }} ? 0 : -1">
                        <picture>
                            @if ($banner->mobile_image_path)
                                <source media="(max-width: 767px)" srcset="{{ $disk->url($banner->mobile_image_path) }}">
                            @endif
                            <img src="{{ $disk->url($banner->image_path) }}" alt="{{ $bannerTitle }}" class="{{ $aspect }} w-full object-cover" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                        </picture>
                    </x-storefront.banner-frame>
                </div>
            @endforeach
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-border bg-surface-band pl-3">
            <p class="min-w-0 flex-1 truncate text-sm font-semibold text-text-primary">
                @foreach ($banners as $banner)
                    <span x-show="current === {{ $loop->index }}" @if (! $loop->first) x-cloak @endif>{{ $banner->title[$locale] ?? ($banner->title[$defaultLocale] ?? '') }}</span>
                @endforeach
            </p>
            <div class="flex items-center">
                <button type="button" x-on:click="prev()" aria-label="{{ __('storefront.home.hero_carousel_prev') }}" class="m-0.5 flex size-11 items-center justify-center rounded-sm border border-border-input bg-surface text-text-primary hover:bg-surface-hover">
                    <x-storefront.icon name="chevron-left" class="size-4" stroke="2" />
                </button>
                <span class="px-3 text-xs text-text-muted tabular-nums" aria-live="off"><span x-text="current + 1">1</span> / {{ $banners->count() }}</span>
                <button type="button" x-on:click="next()" aria-label="{{ __('storefront.home.hero_carousel_next') }}" class="m-0.5 flex size-11 items-center justify-center rounded-sm border border-border-input bg-surface text-text-primary hover:bg-surface-hover">
                    <x-storefront.icon name="chevron-right" class="size-4" stroke="2" />
                </button>
            </div>
        </div>
    </div>
@endif
