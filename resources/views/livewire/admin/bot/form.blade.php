@php
    $locales = config('ribbon.locales');
    $ui = app()->getLocale();
    $label = 'mb-1 block text-sm font-medium text-text-primary';
@endphp
<div class="mx-auto w-full max-w-4xl">
    <x-page-header :title="$answer ? __('admin.bot.edit') : __('admin.bot.new')" :subtitle="__('admin.bot.subtitle')">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.bot.index') }}" wire:navigate variant="ghost">{{ __('admin.ui.cancel') }}</x-button>
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">{{ __('admin.bot.save') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($this->fromQuestion)
        <div class="mb-6 rounded-md border border-info-200 bg-info-50 p-3 text-sm text-info-700"><p>{{ __('admin.bot.from_question', ['text' => $this->fromQuestion->text]) }}</p><p class="mt-1">{{ __('admin.bot.from_question_hint') }}</p></div>
    @endif

    <div class="flex flex-col gap-6">
        {{-- Name + flags --}}
        <div class="rounded-md border border-border-strong bg-surface-raised px-5 py-5">
            <label for="bot-name" class="{{ $label }}">{{ __('admin.bot.name') }} <span class="text-danger-600">*</span></label>
            <x-input id="bot-name" wire:model.blur="name" maxlength="120" :error="$errors->has('name')" />
            @error('name')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <x-toggle wire:model="isStart" :label="__('admin.bot.is_start')" />
                <x-toggle wire:model="isActive" :label="__('admin.bot.is_active')" />
            </div>
        </div>

        {{-- Reply + keywords per locale --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.bot.reply') }} · {{ __('admin.bot.keywords') }}</h2>
            </div>
            <div class="px-5 py-5">
                <x-locale-tabs :incomplete="$this->incompleteLocales" :initial="$this->fromQuestion?->locale">
                    @foreach ($locales as $locale)
                        <div x-show="locale === '{{ $locale }}'" x-cloak class="space-y-4">
                            <div>
                                <label for="bot-reply-{{ $locale }}" class="{{ $label }}">{{ __('admin.bot.reply') }} <span class="text-danger-600">*</span></label>
                                <x-textarea id="bot-reply-{{ $locale }}" rows="5" maxlength="1000" wire:model.blur="reply.{{ $locale }}" :error="$errors->has('reply.'.$locale)" />
                                @error('reply.'.$locale)<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
                                <p class="mt-1 text-xs text-text-muted">{{ __('admin.bot.reply_hint') }}</p>
                            </div>
                            <div>
                                <label for="bot-keywords-{{ $locale }}" class="{{ $label }}">{{ __('admin.bot.keywords') }}</label>
                                <x-textarea id="bot-keywords-{{ $locale }}" rows="4" wire:model.blur="keywords.{{ $locale }}" :error="$errors->has('keywords.'.$locale)" class="font-mono text-sm" />
                                @error('keywords.'.$locale)<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
                                <p class="mt-1 text-xs text-text-muted">{{ __('admin.bot.keywords_hint') }} <a href="{{ route('admin.bot.guide') }}#keywords" target="_blank" class="font-medium text-accent-700 hover:underline">{{ __('admin.bot.guide_link') }}</a></p>
                            </div>
                        </div>
                    @endforeach
                </x-locale-tabs>
            </div>
        </div>

        {{-- Buttons --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.bot.buttons') }}</h2>
                <p class="text-xs text-text-muted">{{ __('admin.bot.buttons_hint') }} <a href="{{ route('admin.bot.guide') }}#buttons" target="_blank" class="font-medium text-accent-700 hover:underline">{{ __('admin.bot.guide_link_buttons') }}</a></p>
            </div>
            <div class="space-y-4 px-5 py-5">
                @foreach ($buttons as $i => $button)
                    @php($action = $button['action'] ?? 'answer')
                    <div wire:key="bot-button-{{ $i }}" class="rounded-sm border border-border p-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="bot-btn-action-{{ $i }}" class="{{ $label }}">{{ __('admin.bot.button_action') }}</label>
                                <x-select id="bot-btn-action-{{ $i }}" wire:model.live="buttons.{{ $i }}.action">
                                    @foreach (\App\Models\BotAnswer::ACTIONS as $option)
                                        <option value="{{ $option }}">{{ __('admin.bot.action_'.$option) }}</option>
                                    @endforeach
                                </x-select>
                            </div>
                            @if (\App\Livewire\Admin\Bot\Form::needsTarget($action))
                                <div>
                                    <label for="bot-btn-target-{{ $i }}" class="{{ $label }}">{{ __('admin.bot.button_target') }}</label>
                                    @if ($action === 'url')
                                        <x-input id="bot-btn-target-{{ $i }}" wire:model.blur="buttons.{{ $i }}.target" placeholder="/contact" class="font-mono" :error="$errors->has('buttons.'.$i.'.target')" />
                                        <p class="mt-1 text-xs text-text-muted">{{ __('admin.bot.url_hint') }}</p>
                                    @else
                                        <x-select id="bot-btn-target-{{ $i }}" wire:model="buttons.{{ $i }}.target" :error="$errors->has('buttons.'.$i.'.target')">
                                            <option value="">{{ __('admin.bot.pick') }}</option>
                                            @if ($action === 'answer')
                                                @foreach ($this->otherAnswers as $other)
                                                    <option value="{{ $other->id }}">{{ $other->name }}</option>
                                                @endforeach
                                            @elseif ($action === 'category')
                                                @foreach ($this->categories as $category)
                                                    <option value="{{ $category->id }}">{{ $category->name[$ui] ?? $category->name[$locales[0]] }}</option>
                                                @endforeach
                                            @else
                                                @foreach ($this->articles as $article)
                                                    <option value="{{ $article->id }}">{{ $article->title[$ui] ?? $article->title[$locales[0]] }}</option>
                                                @endforeach
                                            @endif
                                        </x-select>
                                    @endif
                                    @error('buttons.'.$i.'.target')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
                                </div>
                            @endif
                        </div>

                        <fieldset class="mt-3">
                            <legend class="{{ $label }}">{{ __('admin.bot.button_label') }}</legend>
                            <div class="grid gap-2 sm:grid-cols-3">
                                @foreach ($locales as $locale)
                                    <div>
                                        <x-input aria-label="{{ __('admin.bot.button_label') }} {{ strtoupper($locale) }}" placeholder="{{ strtoupper($locale) }}" maxlength="40" wire:model.blur="buttons.{{ $i }}.label.{{ $locale }}" :error="$errors->has('buttons.'.$i.'.label.'.$locale)" />
                                        @error('buttons.'.$i.'.label.'.$locale)<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-button type="button" variant="ghost" size="sm" wire:click="moveButton({{ $i }}, -1)" :disabled="$loop->first">↑</x-button>
                            <x-button type="button" variant="ghost" size="sm" wire:click="moveButton({{ $i }}, 1)" :disabled="$loop->last">↓</x-button>
                            <x-button type="button" variant="ghost" size="sm" wire:click="removeButton({{ $i }})">{{ __('admin.ui.remove') }}</x-button>
                        </div>
                    </div>
                @endforeach

                @if (count($buttons) < \App\Models\BotAnswer::MAX_BUTTONS)
                    <x-button type="button" variant="secondary" size="sm" wire:click="addButton">{{ __('admin.bot.add_button') }}</x-button>
                @endif
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <x-button tag="a" href="{{ route('admin.bot.index') }}" wire:navigate variant="ghost">{{ __('admin.ui.cancel') }}</x-button>
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">{{ __('admin.bot.save') }}</x-button>
        </div>
    </div>
</div>
