{{-- File picker: hidden native input + secondary button (full width under md).
     Attributes (wire:model, accept, id) go to the input. --}}
@props(['label' => null, 'error' => false])

@php($label ??= __('admin.ui.choose_file'))

<div x-data="{ name: '' }" class="flex flex-col gap-2 md:flex-row md:items-center">
    <input type="file" x-ref="file" x-on:change="name = $refs.file.files[0]?.name ?? ''" {{ $attributes->merge(['class' => 'sr-only']) }}>
    <x-button variant="secondary" class="w-full md:w-auto" x-on:click="$refs.file.click()">{{ $label }}</x-button>
    <span class="min-w-0 truncate font-mono text-xs text-text-muted" x-text="name"></span>
</div>
