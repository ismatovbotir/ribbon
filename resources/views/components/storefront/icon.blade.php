@props(['name', 'stroke' => null])

{{-- Storefront entry point to the shared icon system (App\Support\Icon, Carbon base). `stroke` is accepted for old call sites and ignored. --}}
{!! \App\Support\Icon::svg($name, (string) $attributes->merge(['class' => 'size-5'])) !!}
