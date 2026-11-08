@props([])

{{-- Home reassurance strip (spec 20 8.3). Server-rendered, no headings. Copy lives in lang storefront.trust.* (owner may edit). --}}
@php
    $items = [
        ['icon' => 'security', 'key' => 'verified'],
        ['icon' => 'money', 'key' => 'payment'],
        ['icon' => 'document', 'key' => 'prices'],
        ['icon' => 'chat', 'key' => 'request'],
    ];
@endphp
<section aria-label="{{ __('storefront.trust.label') }}" {{ $attributes->class(['rounded-md border border-border bg-surface-band']) }}>
    <ul class="grid grid-cols-2 lg:grid-cols-4">
        @foreach ($items as $i)
            <li class="flex flex-col items-center gap-3 border-border p-4 text-center max-lg:odd:border-r max-lg:nth-[-n+2]:border-b md:p-6 lg:border-r lg:last:border-r-0">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-sm border border-border bg-surface text-accent-700">
                    <x-storefront.icon :name="$i['icon']" class="size-5" />
                </span>
                <p class="text-sm font-semibold text-balance text-text-primary">{{ __('storefront.trust.'.$i['key']) }}</p>
            </li>
        @endforeach
    </ul>
</section>
