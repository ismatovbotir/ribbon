<div>
    <x-page-header
        :title="__('sellers.analytics.title')"
        :subtitle="__('sellers.analytics.subtitle')"
    />

    @if ($totalViews === 0 && $totalAppearances === 0)
        <div class="rounded-md border border-dashed border-border-strong bg-surface-subtle p-10 text-center">
            <svg class="mx-auto h-8 w-8 text-text-muted" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M3 16.5V7l4.5 3.5L12 5l5 5.5v6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <p class="mt-3 text-sm font-medium text-text-primary">{{ __('sellers.analytics.empty_title') }}</p>
            <p class="mx-auto mt-1 max-w-sm text-sm text-text-secondary">{{ __('sellers.analytics.empty_body') }}</p>
        </div>
    @else
        <p class="mb-4 text-xs font-medium tracking-wide text-text-muted uppercase">{{ __('sellers.analytics.period_label', ['days' => $days]) }}</p>

        {{-- Stat tiles --}}
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-md border border-border bg-surface-raised p-5">
                <p class="text-xs font-medium tracking-wide text-text-muted uppercase">{{ __('sellers.analytics.stat_views_label') }}</p>
                <p class="mt-1.5 text-2xl font-semibold text-text-primary">{{ number_format($totalViews) }}</p>
            </div>
            <div class="rounded-md border border-border bg-surface-raised p-5">
                <p class="text-xs font-medium tracking-wide text-text-muted uppercase">{{ __('sellers.analytics.stat_appearances_label') }}</p>
                <p class="mt-1.5 text-2xl font-semibold text-text-primary">{{ number_format($totalAppearances) }}</p>
            </div>
        </div>

        {{-- Trend chart --}}
        <div class="mb-6 rounded-md border border-border bg-surface-raised p-5" x-data="{ tooltip: null }">
            <div class="mb-3 flex items-center justify-between">
                <p class="text-sm font-semibold text-text-primary">{{ __('sellers.analytics.chart_heading') }}</p>

                {{-- Legend — required for 2 series; line-key + label, never color-only --}}
                <div class="flex items-center gap-4 text-xs text-text-secondary">
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block h-0.5 w-4 rounded-full bg-accent-600"></span>
                        {{ __('sellers.analytics.legend_views') }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block h-0.5 w-4 rounded-full bg-info-600"></span>
                        {{ __('sellers.analytics.legend_appearances') }}
                    </span>
                </div>
            </div>

            <div class="relative">
                <svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" class="w-full" role="img" aria-label="{{ __('sellers.analytics.chart_heading') }}">
                    {{-- Gridlines (recessive, hairline, one-step-off-surface) --}}
                    @foreach ($chart['gridlines'] as $line)
                        <line x1="40" y1="{{ $line['y'] }}" x2="{{ $chart['width'] - 8 }}" y2="{{ $line['y'] }}" class="stroke-border" stroke-width="1" />
                        <text x="36" y="{{ $line['y'] + 3 }}" text-anchor="end" class="fill-text-muted" font-size="10">{{ $line['label'] }}</text>
                    @endforeach

                    {{-- Area fills (~10% opacity wash, never a saturated block) --}}
                    <path d="{{ $chart['appearancesAreaPath'] }}" class="fill-info-600/10" stroke="none" />
                    <path d="{{ $chart['viewsAreaPath'] }}" class="fill-accent-600/10" stroke="none" />

                    {{-- Lines (2px, round join/cap) --}}
                    <path d="{{ $chart['appearancesPath'] }}" class="stroke-info-600" fill="none" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
                    <path d="{{ $chart['viewsPath'] }}" class="stroke-accent-600" fill="none" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

                    {{-- X-axis date labels (sparse — never one per point) --}}
                    @foreach ($chart['xLabels'] as $xLabel)
                        <text x="{{ $xLabel['x'] }}" y="{{ $chart['height'] - 4 }}" text-anchor="middle" class="fill-text-muted" font-size="10">{{ $xLabel['label'] }}</text>
                    @endforeach

                    {{-- Hover targets — hit area bigger than the mark, one per day covering both series --}}
                    @foreach ($chart['points'] as $point)
                        <g
                            x-on:mouseenter="tooltip = { x: {{ $point['x'] }}, label: '{{ $point['label'] }}', views: {{ $point['views'] }}, appearances: {{ $point['appearances'] }} }"
                            x-on:mouseleave="tooltip = null"
                            x-on:focus="tooltip = { x: {{ $point['x'] }}, label: '{{ $point['label'] }}', views: {{ $point['views'] }}, appearances: {{ $point['appearances'] }} }"
                            x-on:blur="tooltip = null"
                            tabindex="0"
                            class="cursor-pointer outline-none"
                        >
                            <rect x="{{ $point['x'] - 12 }}" y="0" width="24" height="{{ $chart['height'] }}" fill="transparent" />
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['yViews'] }}" r="3" class="fill-accent-600" stroke="var(--color-surface-raised)" stroke-width="2" />
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['yAppearances'] }}" r="3" class="fill-info-600" stroke="var(--color-surface-raised)" stroke-width="2" />
                        </g>
                    @endforeach
                </svg>

                {{-- Tooltip — values lead (Strong), series name follows, line-keyed not boxed --}}
                <div
                    x-show="tooltip"
                    x-cloak
                    x-bind:style="tooltip ? `left: ${(tooltip.x / {{ $chart['width'] }}) * 100}%` : ''"
                    class="pointer-events-none absolute top-0 -translate-x-1/2 rounded-sm border border-border bg-surface-overlay px-2.5 py-2 text-xs shadow-md"
                >
                    <p class="font-medium text-text-primary" x-text="tooltip?.label"></p>
                    <p class="mt-1 flex items-center gap-1.5">
                        <span class="inline-block h-0.5 w-3 rounded-full bg-accent-600"></span>
                        <span class="font-semibold text-text-primary" x-text="tooltip?.views"></span>
                        <span class="text-text-secondary">{{ __('sellers.analytics.legend_views') }}</span>
                    </p>
                    <p class="mt-0.5 flex items-center gap-1.5">
                        <span class="inline-block h-0.5 w-3 rounded-full bg-info-600"></span>
                        <span class="font-semibold text-text-primary" x-text="tooltip?.appearances"></span>
                        <span class="text-text-secondary">{{ __('sellers.analytics.legend_appearances') }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            {{-- Traffic source breakdown — horizontal bars, fixed categorical order --}}
            <div class="rounded-md border border-border bg-surface-raised p-5">
                <p class="mb-4 text-sm font-semibold text-text-primary">{{ __('sellers.analytics.source_heading') }}</p>

                @php
                    $sourceColors = [
                        'google' => 'bg-violet-600',
                        'yandex' => 'bg-cyan-600',
                        'internal_search' => 'bg-accent-600',
                        'direct' => 'bg-rose-600',
                        'other' => 'bg-muted-600',
                    ];
                @endphp

                <div class="space-y-3">
                    @foreach (['google', 'yandex', 'internal_search', 'direct', 'other'] as $source)
                        @continue($source === 'other' && $sourceBreakdown['other'] === 0)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="flex items-center gap-1.5 text-text-secondary">
                                    <span class="inline-block h-2 w-2 rounded-full {{ $sourceColors[$source] }}"></span>
                                    {{ __('sellers.analytics.source.'.$source) }}
                                </span>
                                <span class="font-medium text-text-primary tabular-nums">{{ number_format($sourceBreakdown[$source]) }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-surface-sunken">
                                <div class="h-full rounded-full {{ $sourceColors[$source] }}" style="width: {{ $maxSourceCount > 0 ? round($sourceBreakdown[$source] / $maxSourceCount * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Per-product table --}}
            <div class="rounded-md border border-border bg-surface-raised p-5">
                <p class="mb-4 text-sm font-semibold text-text-primary">{{ __('sellers.analytics.table_heading') }}</p>

                @if (empty($perProduct))
                    <p class="text-sm text-text-secondary">{{ __('sellers.analytics.table_empty') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left text-xs font-medium tracking-wide text-text-muted uppercase">
                                    <th class="pb-2">{{ __('sellers.analytics.table.product') }}</th>
                                    <th class="pb-2 text-right">{{ __('sellers.analytics.table.views') }}</th>
                                    <th class="pb-2 text-right">{{ __('sellers.analytics.table.appearances') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($perProduct as $row)
                                    <tr>
                                        <td class="py-2 pr-2 text-text-primary">{{ $row['displayName'] }}</td>
                                        <td class="py-2 text-right font-medium tabular-nums text-text-primary">{{ number_format($row['views']) }}</td>
                                        <td class="py-2 text-right tabular-nums text-text-secondary">{{ number_format($row['appearances']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
