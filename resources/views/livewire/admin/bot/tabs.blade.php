{{-- Bot training sub-navigation: four real links. Included with $current = index|unanswered|test|guide. --}}
<nav aria-label="{{ __('admin.bot.title') }}" class="mb-6 flex gap-1 overflow-x-auto border-b border-border">
    @foreach (['index' => 'tab_answers', 'unanswered' => 'tab_unanswered', 'test' => 'tab_test', 'guide' => 'tab_guide'] as $route => $label)
        <a
            href="{{ route('admin.bot.'.$route) }}"
            wire:navigate
            @if ($current === $route) aria-current="page" @endif
            class="-mb-px flex h-11 shrink-0 items-center border-b-2 px-4 text-sm font-medium {{ $current === $route ? 'border-accent-700 text-accent-700' : 'border-transparent text-text-secondary hover:text-text-primary' }}"
        >{{ __('admin.bot.'.$label) }}</a>
    @endforeach
</nav>
