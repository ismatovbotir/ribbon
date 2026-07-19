<div>
    <x-page-header title="Analytics" subtitle="Platform-wide traffic, product views, and search activity — every seller combined." />

    @if ($totalViews === 0 && $totalSearches === 0)
        <div class="rounded-md border border-dashed border-border-strong bg-surface-subtle p-10 text-center">
            <svg class="mx-auto h-8 w-8 text-text-muted" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M3 16.5V7l4.5 3.5L12 5l5 5.5v6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <p class="mt-3 text-sm font-medium text-text-primary">No activity yet</p>
            <p class="mx-auto mt-1 max-w-sm text-sm text-text-secondary">Traffic and search data will show up here once buyers start browsing the storefront.</p>
        </div>
    @else
        <p class="mb-4 text-xs font-medium tracking-wide text-text-muted uppercase">Last {{ $days }} days</p>

        {{-- Stat tiles --}}
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-md border border-border bg-surface-raised p-5">
                <p class="text-xs font-medium tracking-wide text-text-muted uppercase">Product views</p>
                <p class="mt-1.5 text-2xl font-semibold text-text-primary">{{ number_format($totalViews) }}</p>
            </div>
            <div class="rounded-md border border-border bg-surface-raised p-5">
                <p class="text-xs font-medium tracking-wide text-text-muted uppercase">Searches</p>
                <p class="mt-1.5 text-2xl font-semibold text-text-primary">{{ number_format($totalSearches) }}</p>
            </div>
        </div>

        {{-- Trend chart --}}
        <div class="mb-6 rounded-md border border-border bg-surface-raised p-5" x-data="{ tooltip: null }">
            <div class="mb-3 flex items-center justify-between">
                <p class="text-sm font-semibold text-text-primary">Views &amp; searches over time</p>

                {{-- Legend — required for 2 series; line-key + label, never color-only --}}
                <div class="flex items-center gap-4 text-xs text-text-secondary">
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block h-0.5 w-4 rounded-full bg-accent-600"></span>
                        Product views
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block h-0.5 w-4 rounded-full bg-info-600"></span>
                        Searches
                    </span>
                </div>
            </div>

            <div class="relative">
                <svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" class="w-full" role="img" aria-label="Views and searches over time">
                    {{-- Gridlines (recessive, hairline, one-step-off-surface) --}}
                    @foreach ($chart['gridlines'] as $line)
                        <line x1="40" y1="{{ $line['y'] }}" x2="{{ $chart['width'] - 8 }}" y2="{{ $line['y'] }}" class="stroke-border" stroke-width="1" />
                        <text x="36" y="{{ $line['y'] + 3 }}" text-anchor="end" class="fill-text-muted" font-size="10">{{ $line['label'] }}</text>
                    @endforeach

                    {{-- Area fills (~10% opacity wash, never a saturated block) --}}
                    <path d="{{ $chart['searchesAreaPath'] }}" class="fill-info-600/10" stroke="none" />
                    <path d="{{ $chart['viewsAreaPath'] }}" class="fill-accent-600/10" stroke="none" />

                    {{-- Lines (2px, round join/cap) --}}
                    <path d="{{ $chart['searchesPath'] }}" class="stroke-info-600" fill="none" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
                    <path d="{{ $chart['viewsPath'] }}" class="stroke-accent-600" fill="none" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

                    {{-- X-axis date labels (sparse — never one per point) --}}
                    @foreach ($chart['xLabels'] as $xLabel)
                        <text x="{{ $xLabel['x'] }}" y="{{ $chart['height'] - 4 }}" text-anchor="middle" class="fill-text-muted" font-size="10">{{ $xLabel['label'] }}</text>
                    @endforeach

                    {{-- Hover targets — hit area bigger than the mark, one per day covering both series --}}
                    @foreach ($chart['points'] as $point)
                        <g
                            x-on:mouseenter="tooltip = { x: {{ $point['x'] }}, label: '{{ $point['label'] }}', views: {{ $point['views'] }}, searches: {{ $point['searches'] }} }"
                            x-on:mouseleave="tooltip = null"
                            x-on:focus="tooltip = { x: {{ $point['x'] }}, label: '{{ $point['label'] }}', views: {{ $point['views'] }}, searches: {{ $point['searches'] }} }"
                            x-on:blur="tooltip = null"
                            tabindex="0"
                            class="cursor-pointer outline-none"
                        >
                            <rect x="{{ $point['x'] - 12 }}" y="0" width="24" height="{{ $chart['height'] }}" fill="transparent" />
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['yViews'] }}" r="3" class="fill-accent-600" stroke="var(--color-surface-raised)" stroke-width="2" />
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['ySearches'] }}" r="3" class="fill-info-600" stroke="var(--color-surface-raised)" stroke-width="2" />
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
                        <span class="text-text-secondary">views</span>
                    </p>
                    <p class="mt-0.5 flex items-center gap-1.5">
                        <span class="inline-block h-0.5 w-3 rounded-full bg-info-600"></span>
                        <span class="font-semibold text-text-primary" x-text="tooltip?.searches"></span>
                        <span class="text-text-secondary">searches</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            {{-- Traffic source breakdown — horizontal bars, fixed categorical order,
                 same colors as the seller dashboard's identical chart (consistency
                 across the app, not a fresh color decision). --}}
            <div class="rounded-md border border-border bg-surface-raised p-5">
                <p class="mb-4 text-sm font-semibold text-text-primary">Traffic sources</p>

                @php
                    $sourceColors = [
                        'google' => 'bg-violet-600',
                        'yandex' => 'bg-cyan-600',
                        'internal_search' => 'bg-accent-600',
                        'direct' => 'bg-rose-600',
                        'other' => 'bg-muted-600',
                    ];
                    $sourceLabels = [
                        'google' => 'Google',
                        'yandex' => 'Yandex',
                        'internal_search' => 'Internal search',
                        'direct' => 'Direct',
                        'other' => 'Other',
                    ];
                @endphp

                <div class="space-y-3">
                    @foreach (['google', 'yandex', 'internal_search', 'direct', 'other'] as $source)
                        @continue($source === 'other' && $sourceBreakdown['other'] === 0)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="flex items-center gap-1.5 text-text-secondary">
                                    <span class="inline-block h-2 w-2 rounded-full {{ $sourceColors[$source] }}"></span>
                                    {{ $sourceLabels[$source] }}
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

            {{-- Top searches — single sequential hue (a ranked list, not
                 distinct categories) --}}
            <div class="rounded-md border border-border bg-surface-raised p-5">
                <p class="mb-4 text-sm font-semibold text-text-primary">Top searches</p>

                @if (empty($topQueries))
                    <p class="text-sm text-text-secondary">No searches recorded yet.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($topQueries as $row)
                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span class="truncate text-text-secondary">"{{ $row['query'] }}"</span>
                                    <span class="shrink-0 pl-2 font-medium text-text-primary tabular-nums">{{ number_format($row['count']) }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-surface-sunken">
                                    <div class="h-full rounded-full bg-accent-600" style="width: {{ round($row['count'] / $maxQueryCount * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Top viewed products --}}
        <div class="rounded-md border border-border bg-surface-raised p-5">
            <p class="mb-4 text-sm font-semibold text-text-primary">Top viewed products</p>

            @if (empty($topProducts))
                <p class="text-sm text-text-secondary">No product views recorded yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs font-medium tracking-wide text-text-muted uppercase">
                                <th class="pb-2">Product</th>
                                <th class="pb-2">Seller</th>
                                <th class="pb-2 text-right">Views</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($topProducts as $row)
                                <tr>
                                    <td class="py-2 pr-2 text-text-primary">{{ $row['displayName'] }}</td>
                                    <td class="py-2 pr-2 text-text-secondary">{{ $row['sellerName'] }}</td>
                                    <td class="py-2 text-right font-medium tabular-nums text-text-primary">{{ number_format($row['views']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
