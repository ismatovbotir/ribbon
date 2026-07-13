@props(['banners', 'locale', 'defaultLocale'])

@if ($banners->count() === 1)
    {{-- Single banner: no carousel chrome at all — dots/arrows on one
         slide is a well-known anti-pattern, so this stays a plain static
         banner exactly like before. --}}
    @php $bannerTitle = $banners->first()->title[$locale] ?? ($banners->first()->title[$defaultLocale] ?? ''); @endphp
    <x-storefront.banner-frame :href="$banners->first()->link_url" class="group relative block overflow-hidden rounded-2xl bg-surface-subtle">
        <picture>
            @if ($banners->first()->mobile_image_path)
                <source media="(max-width: 767px)" srcset="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($banners->first()->mobile_image_path) }}">
            @endif
            <img
                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($banners->first()->image_path) }}"
                alt="{{ $bannerTitle }}"
                class="aspect-[21/9] w-full object-cover md:aspect-[3/1]"
                loading="eager"
            >
        </picture>
        @if ($bannerTitle !== '')
            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-900/70 to-transparent p-4 md:p-6">
                <p class="text-lg font-semibold text-white md:text-2xl">{{ $bannerTitle }}</p>
            </div>
        @endif
    </x-storefront.banner-frame>
@else
    <div
        x-data="{
            current: 0,
            total: {{ $banners->count() }},
            timer: null,
            startX: null,
            reduceMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            init() {
                if (! this.reduceMotion) this.play();
            },
            play() {
                this.stop();
                this.timer = setInterval(() => this.next(), 5500);
            },
            stop() {
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
            },
            next() { this.current = (this.current + 1) % this.total; },
            prev() { this.current = (this.current - 1 + this.total) % this.total; },
            goTo(i) { this.current = i; },
            onTouchStart(e) { this.startX = e.touches[0].clientX; this.stop(); },
            onTouchEnd(e) {
                if (this.startX === null) return;
                const delta = e.changedTouches[0].clientX - this.startX;
                if (delta > 40) this.prev();
                else if (delta < -40) this.next();
                this.startX = null;
                if (! this.reduceMotion) this.play();
            },
        }"
        x-on:mouseenter="stop()"
        x-on:mouseleave="if (! reduceMotion) play()"
        x-on:focusin="stop()"
        x-on:focusout="if (! reduceMotion) play()"
        x-on:touchstart="onTouchStart($event)"
        x-on:touchend="onTouchEnd($event)"
        x-on:keydown.left="prev()"
        x-on:keydown.right="next()"
        role="region"
        aria-roledescription="carousel"
        aria-label="{{ __('storefront.home.hero_carousel_label') }}"
        class="group relative overflow-hidden rounded-2xl bg-surface-subtle"
    >
        {{-- Track — all slides always in the DOM (not x-if'd), shifted via
             transform so screen readers/crawlers still see every banner's
             markup; per-slide aria-hidden/tabindex below keep off-screen
             slides out of the tab order and accessibility tree. --}}
        <div class="flex transition-transform duration-500 ease-out" :style="`transform: translateX(-${current * 100}%)`">
            @foreach ($banners as $banner)
                @php $bannerTitle = $banner->title[$locale] ?? ($banner->title[$defaultLocale] ?? ''); @endphp
                <div class="w-full shrink-0" :aria-hidden="current !== {{ $loop->index }} ? 'true' : 'false'">
                    <x-storefront.banner-frame
                        :href="$banner->link_url"
                        class="relative block overflow-hidden bg-surface-subtle"
                        x-bind:tabindex="current === {{ $loop->index }} ? 0 : -1"
                    >
                        <picture>
                            @if ($banner->mobile_image_path)
                                <source media="(max-width: 767px)" srcset="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($banner->mobile_image_path) }}">
                            @endif
                            <img
                                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($banner->image_path) }}"
                                alt="{{ $bannerTitle }}"
                                class="aspect-[21/9] w-full object-cover md:aspect-[3/1]"
                                loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                            >
                        </picture>
                        @if ($bannerTitle !== '')
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-900/70 to-transparent p-4 md:p-6">
                                <p class="text-lg font-semibold text-white md:text-2xl">{{ $bannerTitle }}</p>
                            </div>
                        @endif
                    </x-storefront.banner-frame>
                </div>
            @endforeach
        </div>

        {{-- Arrows — always present (not hover-only: a hover-only control
             is unreachable for touch/keyboard users), subtle by default,
             slightly more opaque on hover/focus. --}}
        <button
            type="button"
            x-on:click="prev()"
            aria-label="{{ __('storefront.home.hero_carousel_prev') }}"
            class="absolute top-1/2 left-3 z-10 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/70 text-slate-900 opacity-80 backdrop-blur transition-opacity hover:opacity-100 focus-visible:opacity-100"
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M12.5 5 7.5 10l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
        <button
            type="button"
            x-on:click="next()"
            aria-label="{{ __('storefront.home.hero_carousel_next') }}"
            class="absolute top-1/2 right-3 z-10 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/70 text-slate-900 opacity-80 backdrop-blur transition-opacity hover:opacity-100 focus-visible:opacity-100"
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M7.5 5l5 5-5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>

        {{-- Dots — the primary nav (direct slide access); arrows above are
             the secondary, one-step control. --}}
        <div class="absolute inset-x-0 bottom-3 z-10 flex items-center justify-center gap-1.5">
            @foreach ($banners as $banner)
                <button
                    type="button"
                    x-on:click="goTo({{ $loop->index }})"
                    x-bind:class="current === {{ $loop->index }} ? 'w-5 bg-white' : 'w-1.5 bg-white/50 hover:bg-white/75'"
                    x-bind:aria-current="current === {{ $loop->index }} ? 'true' : 'false'"
                    aria-label="{{ __('storefront.home.hero_carousel_goto', ['n' => $loop->iteration]) }}"
                    class="h-1.5 rounded-full transition-all"
                ></button>
            @endforeach
        </div>
    </div>
@endif
