{{-- Legacy alias: x-dropdown now renders x-menu (popover at md+, action sheet under md). --}}
@props(['align' => 'right', 'title' => null])

<x-menu :title="$title">
    <x-slot:trigger>{{ $trigger }}</x-slot:trigger>
    {{ $slot }}
</x-menu>
