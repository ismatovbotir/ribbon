<?php

namespace App\Livewire\Admin\Analytics;

use App\Services\PlatformAnalyticsService;
use App\Support\LocalizedDate;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Platform-wide analytics — traffic sources, top viewed products, top
 * search queries, over the last 30 days. Super Admin only (see
 * routes/web.php). The chart geometry here is a deliberate copy of
 * Sellers\Analytics\Show's line-chart building (same viewBox, same
 * niceMax rounding, same dataviz mark specs) rather than a shared
 * abstraction — two call sites isn't enough to justify extracting one yet,
 * and the two pages' data shapes differ (views/appearances vs.
 * views/searches).
 */
class Show extends Component
{
    private const DAYS = 30;

    /**
     * @param  array{labels: array<int, string>, views: array<int, int>, searches: array<int, int>}  $daily
     * @return array{width: int, height: int, gridlines: array<int, array{y: float, label: string}>, viewsPath: string, viewsAreaPath: string, searchesPath: string, searchesAreaPath: string, points: array<int, array{x: float, yViews: float, ySearches: float, label: string, views: int, searches: int}>, xLabels: array<int, array{x: float, label: string}>, baselineY: float}
     */
    private function lineChartGeometry(array $daily): array
    {
        $width = 720;
        $height = 220;
        $padLeft = 40;
        $padRight = 8;
        $padTop = 12;
        $padBottom = 24;
        $plotWidth = $width - $padLeft - $padRight;
        $plotHeight = $height - $padTop - $padBottom;
        $baselineY = $padTop + $plotHeight;

        $count = count($daily['labels']);
        $rawMax = max(1, max($daily['views'] ?: [0]), max($daily['searches'] ?: [0]));
        $niceMax = $this->niceMax($rawMax);

        $x = fn (int $i) => $count > 1 ? $padLeft + ($i / ($count - 1)) * $plotWidth : $padLeft;
        $y = fn (int $v) => $padTop + $plotHeight * (1 - $v / $niceMax);

        $points = [];

        foreach ($daily['labels'] as $i => $dateKey) {
            $points[] = [
                'x' => $x($i),
                'yViews' => $y($daily['views'][$i]),
                'ySearches' => $y($daily['searches'][$i]),
                'label' => LocalizedDate::monthDay(Carbon::parse($dateKey)),
                'views' => $daily['views'][$i],
                'searches' => $daily['searches'][$i],
            ];
        }

        $buildPath = fn (string $key) => 'M'.collect($points)
            ->map(fn (array $p) => $p['x'].','.$p[$key])
            ->implode(' L');

        $buildAreaPath = fn (string $key) => $buildPath($key)
            .' L'.$points[$count - 1]['x'].','.$baselineY
            .' L'.$points[0]['x'].','.$baselineY.' Z';

        $gridlines = [];

        foreach ([0, 0.5, 1] as $fraction) {
            $gridlines[] = [
                'y' => $y((int) round($niceMax * $fraction)),
                'label' => number_format($niceMax * $fraction),
            ];
        }

        $xLabelIndexes = $count > 1
            ? array_unique(array_map(fn (int $i) => min($i, $count - 1), [0, (int) round(($count - 1) / 4), (int) round(2 * ($count - 1) / 4), (int) round(3 * ($count - 1) / 4), $count - 1]))
            : [0];

        $xLabels = collect($xLabelIndexes)
            ->map(fn (int $i) => ['x' => $points[$i]['x'], 'label' => $points[$i]['label']])
            ->values()
            ->all();

        return [
            'width' => $width,
            'height' => $height,
            'gridlines' => $gridlines,
            'viewsPath' => $buildPath('yViews'),
            'viewsAreaPath' => $buildAreaPath('yViews'),
            'searchesPath' => $buildPath('ySearches'),
            'searchesAreaPath' => $buildAreaPath('ySearches'),
            'points' => $points,
            'xLabels' => $xLabels,
            'baselineY' => $baselineY,
        ];
    }

    /**
     * Rounds $value up to a "clean" axis ceiling — 5, 10, 20, 50, 100, ... —
     * per the dataviz mark spec ("Y-axis ticks: round to clean numbers").
     */
    private function niceMax(int $value): int
    {
        if ($value <= 5) {
            return 5;
        }

        $magnitude = 10 ** floor(log10($value));
        $normalized = $value / $magnitude;

        $niceNormalized = match (true) {
            $normalized <= 1 => 1,
            $normalized <= 2 => 2,
            $normalized <= 5 => 5,
            default => 10,
        };

        return (int) ($niceNormalized * $magnitude);
    }

    public function render()
    {
        $daily = PlatformAnalyticsService::dailyCounts(self::DAYS);
        $sourceBreakdown = PlatformAnalyticsService::sourceBreakdown(self::DAYS);
        $topProducts = PlatformAnalyticsService::topViewedProducts(self::DAYS);
        $topQueries = PlatformAnalyticsService::topSearchQueries(self::DAYS);

        $maxSourceCount = max(1, ...array_values($sourceBreakdown));
        $maxQueryCount = $topQueries === [] ? 1 : max(array_column($topQueries, 'count'));

        return view('livewire.admin.analytics.show', [
            'chart' => $this->lineChartGeometry($daily),
            'sourceBreakdown' => $sourceBreakdown,
            'maxSourceCount' => $maxSourceCount,
            'topProducts' => $topProducts,
            'topQueries' => $topQueries,
            'maxQueryCount' => $maxQueryCount,
            'totalViews' => array_sum($daily['views']),
            'totalSearches' => array_sum($daily['searches']),
            'days' => self::DAYS,
        ])->layout('layouts.admin', [
            'title' => 'Analytics',
            'breadcrumb' => [
                ['label' => 'Analytics'],
            ],
        ]);
    }
}
