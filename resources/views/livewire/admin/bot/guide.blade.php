{{-- Bot training > Instructions. Sections come from lang admin.bot.guide: [{id, title, intro?, steps?|points?, tip?}]. --}}
<div>
    <x-page-header :title="__('admin.bot.title')" :subtitle="__('admin.bot.guide_subtitle')" />

    {{-- Header and tabs at full width like the other bot tabs (no sideways jump); only the reading column is narrowed. --}}
    @include('livewire.admin.bot.tabs', ['current' => 'guide'])

    <div class="mx-auto w-full max-w-4xl">

    <nav aria-labelledby="guide-contents" class="mb-6 rounded-md border border-border bg-surface-subtle p-4">
        <p id="guide-contents" class="text-xs font-semibold tracking-[0.04em] text-text-muted uppercase">{{ __('admin.bot.guide_contents') }}</p>
        <ol class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
            @foreach ($sections as $n => $section)
                <li><a href="#{{ $section['id'] }}" class="inline-flex min-h-8 items-center text-accent-700 hover:underline">{{ $n + 1 }}. {{ $section['title'] }}</a></li>
            @endforeach
        </ol>
    </nav>

    <div class="flex flex-col gap-4">
        @foreach ($sections as $n => $section)
            <section id="{{ $section['id'] }}" aria-labelledby="{{ $section['id'] }}-title" class="scroll-mt-20 rounded-md border border-border-strong bg-surface-raised">
                <div class="border-b border-border bg-surface-subtle px-5 py-3">
                    <h2 id="{{ $section['id'] }}-title" class="text-lg font-semibold text-text-primary">{{ $n + 1 }}. {{ $section['title'] }}</h2>
                </div>
                <div class="space-y-3 px-5 py-4 text-sm leading-6 text-text-primary">
                    @if (! empty($section['intro']))
                        <p>{{ $section['intro'] }}</p>
                    @endif

                    @if (! empty($section['steps']))
                        <ol class="space-y-2">
                            @foreach ($section['steps'] as $s => $step)
                                <li class="flex gap-3">
                                    <span aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-sm bg-accent-700 font-mono text-xs font-semibold text-white">{{ $s + 1 }}</span>
                                    <span class="pt-0.5">{{ $step }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @if (! empty($section['points']))
                        <ul class="list-disc space-y-1.5 pl-5 marker:text-text-muted">
                            @foreach ($section['points'] as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! empty($section['tip']))
                        <p class="rounded-sm border-l-[3px] border-accent-700 bg-surface-subtle px-3 py-2 text-text-secondary">{{ $section['tip'] }}</p>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
    </div>
</div>
