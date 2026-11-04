{{-- One form for create and edit ($page is null on create). Admin panel: straight, violet (spec 16). --}}
@php
    $isSystemPage = (bool) $page?->key;
    $incomplete = collect(config('ribbon.locales'))
        ->filter(fn ($l) => trim((string) ($title[$l] ?? '')) === '' || trim(strip_tags((string) ($body[$l] ?? ''))) === '')
        ->values()
        ->all();
    $isSuperAdmin = Auth::user()->isSuperAdmin();
@endphp
<div class="max-w-4xl">
    <x-page-header :title="$page ? __('admin.cms.pages.form_edit') : __('admin.cms.pages.form_new')" :subtitle="__('admin.cms.pages.form_subtitle')">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.pages.index') }}" wire:navigate variant="ghost">← {{ __('admin.cms.pages.back') }}</x-button>
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">{{ __('admin.cms.pages.save') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-col gap-6">
        {{-- Content --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.cms.pages.section_content') }}</h2>
            </div>
            <div class="px-5 py-5">
                <x-locale-tabs :incomplete="$incomplete">
                    @foreach (config('ribbon.locales') as $locale)
                        <div x-show="locale === '{{ $locale }}'" x-cloak role="tabpanel" class="space-y-4">
                            <div>
                                <label for="page-title-{{ $locale }}" class="mb-1 block text-sm font-medium text-text-primary">
                                    {{ __('admin.cms.pages.field_title') }} <span class="text-danger-600">*</span>
                                </label>
                                <x-input id="page-title-{{ $locale }}" type="text" wire:model.live.debounce.400ms="title.{{ $locale }}" :error="$errors->has('title.'.$locale)" />
                                @error('title.'.$locale)
                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="page-lead-{{ $locale }}" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.cms.pages.field_lead') }}</label>
                                <x-textarea id="page-lead-{{ $locale }}" wire:model.blur="lead.{{ $locale }}" rows="2" maxlength="300" :error="$errors->has('lead.'.$locale)" />
                                @error('lead.'.$locale)
                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-text-muted">{{ __('admin.cms.pages.field_lead_hint') }}</p>
                            </div>

                            <div>
                                <p class="mb-1 block text-sm font-medium text-text-primary">
                                    {{ __('admin.cms.pages.field_body') }} <span class="text-danger-600">*</span>
                                </p>
                                <x-rich-text-editor name="body.{{ $locale }}" :value="$body[$locale]" :error="$errors->has('body.'.$locale)" />
                                @error('body.'.$locale)
                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-text-muted">{{ __('admin.cms.pages.field_body_hint') }}</p>
                            </div>

                            <div>
                                <label for="page-meta-{{ $locale }}" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.cms.pages.field_meta') }}</label>
                                <x-textarea id="page-meta-{{ $locale }}" wire:model.live.debounce.300ms="metaDescription.{{ $locale }}" rows="2" maxlength="160" :error="$errors->has('metaDescription.'.$locale)" />
                                <div class="mt-1 flex items-start justify-between gap-3">
                                    <p class="text-xs text-text-muted">{{ __('admin.cms.pages.field_meta_hint') }}</p>
                                    <p class="shrink-0 font-mono text-xs text-text-muted tabular-nums">{{ mb_strlen($metaDescription[$locale] ?? '') }}/160</p>
                                </div>
                                @error('metaDescription.'.$locale)
                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endforeach
                </x-locale-tabs>
            </div>
        </div>

        {{-- Placement --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.cms.pages.section_placement') }}</h2>
            </div>
            <div class="space-y-4 px-5 py-5">
                <x-toggle wire:model="isPublished" :label="__('admin.cms.pages.field_published')" :caption="__('admin.cms.pages.field_published_caption')" />

                <div>
                    <x-toggle wire:model="showInHeader" :label="__('admin.cms.pages.field_header')" :caption="__('admin.cms.pages.field_header_caption')" />
                    @error('showInHeader')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <x-toggle wire:model.live="showInFooter" :label="__('admin.cms.pages.field_footer')" :caption="__('admin.cms.pages.field_footer_caption')" />

                @if ($showInFooter)
                    <div>
                        <label for="page-column" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.cms.pages.field_column') }}</label>
                        <x-select id="page-column" wire:model="footerColumn" :error="$errors->has('footerColumn')" class="max-w-xs">
                            <option value="company">{{ __('admin.cms.pages.column_company') }}</option>
                            @if ($isSuperAdmin || $footerColumn === 'legal')
                                <option value="legal" @disabled(! $isSuperAdmin)>{{ __('admin.cms.pages.column_legal') }}</option>
                            @endif
                        </x-select>
                        @error('footerColumn')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div>
                    <label for="page-sort" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.cms.pages.field_sort') }}</label>
                    <x-input id="page-sort" type="number" min="0" max="65535" inputmode="numeric" wire:model.blur="sortOrder" :error="$errors->has('sortOrder')" class="max-w-32 font-mono" />
                    @error('sortOrder')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">{{ __('admin.cms.pages.field_sort_hint') }}</p>
                </div>
            </div>
        </div>

        {{-- URL (read-only) --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.cms.pages.section_url') }}</h2>
            </div>
            <div class="px-5 py-5">
                <ul class="space-y-1">
                    @foreach (config('ribbon.locales') as $locale)
                        <li class="flex items-baseline gap-3 text-sm">
                            <span class="w-8 font-mono text-xs text-text-muted uppercase">{{ $locale }}</span>
                            <span class="font-mono text-text-primary">
                                @if ($isSystemPage)
                                    {{ $page->path($locale) }}
                                @else
                                    /p/{{ $this->slugPreview($locale) ?: '…' }}
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-text-muted">{{ $isSystemPage ? __('admin.cms.pages.url_system') : __('admin.cms.pages.url_custom') }}</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <x-button tag="a" href="{{ route('admin.pages.index') }}" wire:navigate variant="ghost">{{ __('admin.cms.pages.cancel') }}</x-button>
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">{{ __('admin.cms.pages.save') }}</x-button>
        </div>
    </div>
</div>
