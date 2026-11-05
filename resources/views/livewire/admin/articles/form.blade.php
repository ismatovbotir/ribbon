{{--
    Single form used for both Create and Edit ($article is null on create).
    Every field keeps the exact same wire:model binding regardless of mode —
    see Form::class docblock.
--}}
<div class="max-w-4xl">
    <x-page-header :title="$article ? __('admin.ui.edit_article') : __('admin.ui.new_article')" :subtitle="__('admin.ui.educational_content_shown_on_the_storefront_s_articles_secti')">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.articles.index') }}" wire:navigate variant="ghost">
                {{ __('admin.ui.all_articles') }}
            </x-button>
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save, coverImageUpload">
                {{ __('admin.ui.save_article') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-6">
        {{-- Type --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.type') }}</h2>
            </div>
            <div class="px-5 py-5">
                <label for="field-type" class="mb-1 block text-sm font-medium text-text-primary">
                    {{ __('admin.ui.type') }} <span class="text-danger-600">*</span>
                </label>
                <x-select id="field-type" wire:model="type" :error="$errors->has('type')" class="max-w-xs">
                    <option value="article">{{ __('admin.ui.article') }}</option>
                    <option value="news">{{ __('admin.ui.news') }}</option>
                </x-select>
                @error('type')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.article_evergreen_guides_history_technical_explainers_news_t') }}</p>

                <label for="field-categories" class="mt-4 mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.categories') }}</label>
                @php
                    $categoryOptions = $this->categories->map(fn ($category) => [
                        'id' => (string) $category->id,
                        'name' => $category->name[app()->getLocale()] ?? $category->name[config('ribbon.locales')[0]],
                    ]);
                @endphp
                <div
                    x-data="{
                        options: @js($categoryOptions),
                        selectedIds: @js($categoryIds),
                        query: '',
                        open: false,
                        get selected() {
                            return this.selectedIds.map(id => this.options.find(o => o.id === id)).filter(Boolean);
                        },
                        get filtered() {
                            const q = this.query.trim().toLowerCase();
                            return this.options.filter(o => ! this.selectedIds.includes(o.id) && (q === '' || o.name.toLowerCase().includes(q)));
                        },
                        sync() {
                            $wire.set('categoryIds', this.selectedIds, false);
                        },
                        add(id) {
                            this.selectedIds.push(id);
                            this.query = '';
                            this.sync();
                            this.$refs.tagInput.focus();
                        },
                        remove(id) {
                            this.selectedIds = this.selectedIds.filter(existing => existing !== id);
                            this.sync();
                        },
                    }"
                    class="relative"
                >
                    <div
                        @click="$refs.tagInput.focus()"
                        class="flex flex-wrap items-center gap-1.5 rounded-sm border border-border bg-surface p-2 focus-within:border-accent-500 focus-within:ring-2 focus-within:ring-accent-100"
                    >
                        <template x-for="category in selected" :key="category.id">
                            <span class="inline-flex items-center gap-1 rounded-pill bg-accent-50 py-0.5 pr-1 pl-2.5 text-xs font-medium whitespace-nowrap text-accent-700">
                                <span x-text="category.name"></span>
                                <button type="button" @click.stop="remove(category.id)" class="rounded-pill p-0.5 text-accent-500 hover:bg-accent-100 hover:text-accent-700">
                                    <span class="sr-only">{{ __('admin.ui.remove') }}</span>
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </span>
                        </template>
                        <input
                            x-ref="tagInput"
                            id="field-categories"
                            type="text"
                            x-model="query"
                            @focus="open = true"
                            @keydown.escape="open = false"
                            @keydown.enter.prevent="if (filtered.length) add(filtered[0].id)"
                            @keydown.backspace="if (query === '' && selectedIds.length) remove(selectedIds[selectedIds.length - 1])"
                            :placeholder="selected.length ? '' : @js(__('admin.ui.type_to_search_categories'))"
                            class="min-w-[10rem] flex-1 border-0 bg-transparent p-0.5 text-sm text-text-primary placeholder:text-text-muted"
                            autocomplete="off"
                        >
                    </div>

                    <div
                        x-show="open && filtered.length > 0"
                        x-cloak
                        @click.outside="open = false"
                        class="absolute z-(--z-dropdown) mt-1 max-h-48 w-full overflow-y-auto rounded-sm border border-border-strong bg-surface-raised"
                    >
                        <template x-for="category in filtered" :key="category.id">
                            <button
                                type="button"
                                @click="add(category.id)"
                                class="block w-full px-3 py-2 text-left text-sm text-text-primary hover:bg-surface-hover"
                                x-text="category.name"
                            ></button>
                        </template>
                    </div>
                </div>
                @error('categoryIds')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror
                @error('categoryIds.*')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.optional_shows_this_article_as_a_related_article_on_each_tag') }}</p>
            </div>
        </div>

        {{-- Content --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.content') }}</h2>
            </div>
            <div class="px-5 py-5">
                <x-locale-tabs :incomplete="$this->incompleteLocales">
                    @foreach (config('ribbon.locales') as $locale)
                        <div x-show="locale === '{{ $locale }}'" x-cloak class="space-y-4">
                            <div>
                                <label for="field-title-{{ $locale }}" class="mb-1 block text-sm font-medium text-text-primary">
                                    {{ __('admin.ui.title') }} <span class="text-danger-600">*</span>
                                </label>
                                <x-input id="field-title-{{ $locale }}" type="text" wire:model.live.debounce.400ms="title.{{ $locale }}" :error="$errors->has('title.'.$locale)" :placeholder="__('admin.ui.e_g_the_history_of_thermal_transfer_ribbons')" />
                                @error('title.'.$locale)
                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 font-mono text-xs text-text-muted">/articles/{{ $this->slugPreview($locale) ?: '…' }}</p>
                            </div>

                            <div>
                                <label for="field-excerpt-{{ $locale }}" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.excerpt') }}</label>
                                <textarea id="field-excerpt-{{ $locale }}"
                                    wire:model.blur="excerpt.{{ $locale }}"
                                    rows="2"
                                    placeholder="{{ __('admin.ui.short_teaser_shown_on_the_home_page_and_articles_list') }}"
                                    class="block w-full rounded-sm border bg-surface px-3 py-2 text-base text-text-primary placeholder:text-text-muted focus:border-accent-500 {{ $errors->has('excerpt.'.$locale) ? 'border-danger-600' : 'border-border' }}"
                                ></textarea>
                                @error('excerpt.'.$locale)
                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.optional_falls_back_to_a_truncated_body_on_the_storefront_if') }}</p>
                            </div>

                            <div>
                                <label id="field-body-{{ $locale }}" class="mb-1 block text-sm font-medium text-text-primary">
                                    {{ __('admin.ui.body') }} <span class="text-danger-600">*</span>
                                </label>
                                <x-rich-text-editor labelled-by="field-body-{{ $locale }}" name="body.{{ $locale }}" :value="$body[$locale]" :error="$errors->has('body.'.$locale)" />
                                @error('body.'.$locale)
                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.use_the_toolbar_to_format_text_and_drag_or_paste_images_dire') }}</p>
                            </div>
                        </div>
                    @endforeach
                </x-locale-tabs>
            </div>
        </div>

        {{-- Cover image --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.cover_image') }}</h2>
            </div>
            <div class="px-5 py-5">
                @if ($coverImageUpload)
                    <img src="{{ $coverImageUpload->temporaryUrl() }}" alt="{{ __('admin.ui.new_cover_image_preview') }}" class="mb-2 h-40 w-full max-w-md rounded-sm border border-border object-cover">
                @elseif ($existingCoverImagePath)
                    <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($existingCoverImagePath) }}" alt="{{ __('admin.ui.current_cover_image') }}" class="mb-2 h-40 w-full max-w-md rounded-sm border border-border object-cover">
                @endif

                <input id="field-coverImageUpload" aria-label="{{ __('admin.ui.cover_image') }}" type="file" wire:model="coverImageUpload" accept="image/*" class="block w-full max-w-md text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">

                <div wire:loading wire:target="coverImageUpload" class="mt-1 text-xs text-text-muted">{{ __('admin.ui.uploading') }}</div>

                @error('coverImageUpload')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.optional_max_4mb') }}</p>

                @if ($existingCoverImagePath || $coverImageUpload)
                    <button type="button" wire:click="removeCoverImage" class="mt-1 text-xs text-danger-600 hover:underline">{{ __('admin.ui.remove_cover_image') }}</button>
                @endif
            </div>
        </div>

        {{-- Publishing --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.publishing') }}</h2>
            </div>
            <div class="px-5 py-5">
                <label for="field-publishedAt" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.published_at') }}</label>
                <x-input id="field-publishedAt" type="datetime-local" wire:model.blur="publishedAt" :error="$errors->has('publishedAt')" class="max-w-xs" />
                @error('publishedAt')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.leave_blank_to_keep_this_a_draft_never_shown_on_the_storefro') }}</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <x-button tag="a" href="{{ route('admin.articles.index') }}" wire:navigate variant="ghost">{{ __('admin.ui.cancel') }}</x-button>
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save, coverImageUpload">
                {{ __('admin.ui.save_article') }}
            </x-button>
        </div>
    </div>
</div>
