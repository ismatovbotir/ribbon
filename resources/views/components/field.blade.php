{{-- Label + control + hint + error. The control inside should carry id="{{ $for }}"
     and, ideally, aria-invalid / aria-describedby (see below). --}}
@props(['label', 'for', 'required' => false, 'hint' => null, 'error' => null])

<div {{ $attributes->merge(['class' => 'scroll-mt-16 scroll-mb-24']) }}>
    <label for="{{ $for }}" class="mb-1.5 block text-sm font-medium text-text-primary">
        {{ $label }}
        @if ($required)
            <span class="text-danger-700" aria-hidden="true">*</span><span class="sr-only"> {{ __('validation.required_mark') }}</span>
        @endif
    </label>
    {{ $slot }}
    @if ($hint)
        <p id="{{ $for }}-hint" class="mt-1.5 text-xs text-text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $for }}-error" class="mt-1.5 flex items-start gap-1.5 text-xs font-medium text-danger-700">
            <x-icon name="warning-alt" class="mt-px size-3.5" />
            <span>{{ $error }}</span>
        </p>
    @endif
</div>
