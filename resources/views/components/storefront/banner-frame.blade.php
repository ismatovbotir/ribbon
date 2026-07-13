@props(['href' => null])

{{--
    Wraps a promo banner's image/caption content in a real <a> when it has
    a link_url, or a plain non-interactive <div> when it doesn't — an
    anchor with a dummy `href="#"` would be a focusable no-op tab stop for
    keyboard/screen-reader users, not just a cosmetic concern.
--}}
@if ($href)
    <a href="{{ $href }}" {{ $attributes }}>
        {{ $slot }}
    </a>
@else
    <div {{ $attributes }}>
        {{ $slot }}
    </div>
@endif
