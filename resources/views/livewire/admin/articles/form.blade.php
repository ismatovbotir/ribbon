{{--
    Single form used for both Create and Edit ($article is null on create).
    Every field keeps the exact same wire:model binding regardless of mode —
    see Form::class docblock.
--}}
<div class="max-w-4xl">
    <x-page-header :title="$article ? 'Edit Article' : 'New Article'" subtitle="Educational content shown on the storefront's Articles section.">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.articles.index') }}" wire:navigate variant="ghost">
                ← All articles
            </x-button>
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save, coverImageUpload">
                Save Article
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
                <h2 class="text-lg font-semibold text-text-primary">Type</h2>
            </div>
            <div class="px-5 py-5">
                <label class="mb-1 block text-sm font-medium text-text-primary">
                    Type <span class="text-danger-600">*</span>
                </label>
                <x-select wire:model="type" :error="$errors->has('type')" class="max-w-xs">
                    <option value="article">Article</option>
                    <option value="news">News</option>
                </x-select>
                @error('type')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-text-muted">Article — evergreen guides, history, technical explainers. News — time-sensitive announcements.</p>

                <label class="mt-4 mb-1 block text-sm font-medium text-text-primary">Categories</label>
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
                            <span class="inline-flex items-center gap-1 rounded-full bg-accent-50 py-0.5 pr-1 pl-2.5 text-xs font-medium whitespace-nowrap text-accent-700">
                                <span x-text="category.name"></span>
                                <button type="button" @click.stop="remove(category.id)" class="rounded-full p-0.5 text-accent-500 hover:bg-accent-100 hover:text-accent-700">
                                    <span class="sr-only">Remove</span>
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </span>
                        </template>
                        <input
                            x-ref="tagInput"
                            type="text"
                            x-model="query"
                            @focus="open = true"
                            @keydown.escape="open = false"
                            @keydown.enter.prevent="if (filtered.length) add(filtered[0].id)"
                            @keydown.backspace="if (query === '' && selectedIds.length) remove(selectedIds[selectedIds.length - 1])"
                            :placeholder="selected.length ? '' : 'Type to search categories…'"
                            class="min-w-[10rem] flex-1 border-none bg-transparent p-0.5 text-sm text-text-primary placeholder:text-text-muted focus:ring-0 focus:outline-none"
                            autocomplete="off"
                        >
                    </div>

                    <div
                        x-show="open && filtered.length > 0"
                        x-cloak
                        @click.outside="open = false"
                        class="absolute z-dropdown mt-1 max-h-48 w-full overflow-y-auto rounded-sm border border-border-strong bg-surface-raised shadow-lg"
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
                <p class="mt-1 text-xs text-text-muted">Optional — shows this article as a "related article" on each tagged category's storefront page.</p>
            </div>
        </div>

        {{-- Content --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">Content</h2>
            </div>
            <div class="px-5 py-5">
                <x-locale-tabs :incomplete="$this->incompleteLocales">
                    @foreach (config('ribbon.locales') as $locale)
                        <div x-show="locale === '{{ $locale }}'" x-cloak class="space-y-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-text-primary">
                                    Title <span class="text-danger-600">*</span>
                                </label>
                                <x-input type="text" wire:model.live.debounce.400ms="title.{{ $locale }}" :error="$errors->has('title.'.$locale)" placeholder="e.g. The history of thermal transfer ribbons" />
                                @error('title.'.$locale)
                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 font-mono text-xs text-text-muted">/articles/{{ $this->slugPreview($locale) ?: '…' }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-text-primary">Excerpt</label>
                                <textarea
                                    wire:model.blur="excerpt.{{ $locale }}"
                                    rows="2"
                                    placeholder="Short teaser shown on the home page and articles list"
                                    class="block w-full rounded-sm border bg-surface px-3 py-2 text-base text-text-primary placeholder:text-text-muted focus:border-accent-500 focus:ring-2 focus:ring-accent-100 focus:outline-none {{ $errors->has('excerpt.'.$locale) ? 'border-danger-600' : 'border-border' }}"
                                ></textarea>
                                @error('excerpt.'.$locale)
                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-text-muted">Optional — falls back to a truncated body on the storefront if left blank.</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-text-primary">
                                    Body <span class="text-danger-600">*</span>
                                </label>
                                <x-rich-text-editor name="body.{{ $locale }}" :value="$body[$locale]" :error="$errors->has('body.'.$locale)" />
                                @error('body.'.$locale)
                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-text-muted">Use the toolbar to format text and drag or paste images directly into the body.</p>
                            </div>
                        </div>
                    @endforeach
                </x-locale-tabs>
            </div>
        </div>

        {{-- Cover image --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">Cover image</h2>
            </div>
            <div class="px-5 py-5">
                @if ($coverImageUpload)
                    <img src="{{ $coverImageUpload->temporaryUrl() }}" alt="New cover image preview" class="mb-2 h-40 w-full max-w-md rounded-sm border border-border object-cover">
                @elseif ($existingCoverImagePath)
                    <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($existingCoverImagePath) }}" alt="Current cover image" class="mb-2 h-40 w-full max-w-md rounded-sm border border-border object-cover">
                @endif

                <input type="file" wire:model="coverImageUpload" accept="image/*" class="block w-full max-w-md text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">

                <div wire:loading wire:target="coverImageUpload" class="mt-1 text-xs text-text-muted">Uploading…</div>

                @error('coverImageUpload')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-text-muted">Optional. Max 4MB.</p>

                @if ($existingCoverImagePath || $coverImageUpload)
                    <button type="button" wire:click="removeCoverImage" class="mt-1 text-xs text-danger-600 hover:underline">Remove cover image</button>
                @endif
            </div>
        </div>

        {{-- Publishing --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">Publishing</h2>
            </div>
            <div class="px-5 py-5">
                <label class="mb-1 block text-sm font-medium text-text-primary">Published at</label>
                <x-input type="datetime-local" wire:model.blur="publishedAt" :error="$errors->has('publishedAt')" class="max-w-xs" />
                @error('publishedAt')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-text-muted">Leave blank to keep this a draft (never shown on the storefront). A future date/time schedules it; a past date/time publishes it immediately.</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <x-button tag="a" href="{{ route('admin.articles.index') }}" wire:navigate variant="ghost">Cancel</x-button>
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save, coverImageUpload">
                Save Article
            </x-button>
        </div>
    </div>
</div>
