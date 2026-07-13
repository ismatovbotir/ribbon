<?php

namespace App\Livewire\Sellers\Analytics;

use App\Models\Seller;
use App\Services\ProductAnalyticsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Seller-facing product performance dashboard — 30-day view/impression
 * trend, traffic-source breakdown, and a per-product table. Read-only,
 * built entirely from ProductAnalyticsService's aggregation methods; this
 * class only shapes that data into SVG-ready chart geometry, no querying
 * of its own beyond that.
 */
class Show extends Component
{
    private const DAYS = 30;

    /**
     * Reached only via the `seller.auth` middleware, which already
     * guarantees the authenticated user is linked to an `approved` Seller
     * — calling sellerOrFail() directly here is safe, mirrors Dashboard.
     */
    public function seller(): Seller
    {
        return Auth::user()->sellerOrFail();
    }

    /**
     * Line-chart geometry for the 30-day views/appearances trend — two
     * series sharing one axis (both are plain counts, same scale), per the
     * dataviz "never dual-axis" rule. viewBox is a fixed 720x220 that
     * scales responsively via CSS; niceMax rounds the y-axis ceiling to a
     * clean number (5/10/20/50/100/...) rather than the raw data max.
     *
     * @param  array{labels: array<int, string>, views: array<int, int>, appearances: array<int, int>}  $daily
     * @return array{width: int, height: int, gridlines: array<int, array{y: float, label: string}>, viewsPath: string, viewsAreaPath: string, appearancesPath: string, appearancesAreaPath: string, points: array<int, array{x: float, y: float, label: string, views: int, appearances: int}>, xLabels: array<int, array{x: float, label: string}>, baselineY: float}
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
        $rawMax = max(1, max($daily['views'] ?: [0]), max($daily['appearances'] ?: [0]));
        $niceMax = $this->niceMax($rawMax);

        $x = fn (int $i) => $count > 1 ? $padLeft + ($i / ($count - 1)) * $plotWidth : $padLeft;
        $y = fn (int $v) => $padTop + $plotHeight * (1 - $v / $niceMax);

        $points = [];

        foreach ($daily['labels'] as $i => $dateKey) {
            $points[] = [
                'x' => $x($i),
                'yViews' => $y($daily['views'][$i]),
                'yAppearances' => $y($daily['appearances'][$i]),
                'label' => \Illuminate\Support\Carbon::parse($dateKey)->translatedFormat('M j'),
                'views' => $daily['views'][$i],
                'appearances' => $daily['appearances'][$i],
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
            'appearancesPath' => $buildPath('yAppearances'),
            'appearancesAreaPath' => $buildAreaPath('yAppearances'),
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
        $seller = $this->seller();

        $daily = ProductAnalyticsService::dailyCounts($seller, self::DAYS);
        $sourceBreakdown = ProductAnalyticsService::sourceBreakdown($seller, self::DAYS);
        $perProduct = ProductAnalyticsService::perProductTotals($seller, self::DAYS);

        $maxSourceCount = max(1, ...array_values($sourceBreakdown));

        return view('livewire.sellers.analytics.show', [
            'chart' => $this->lineChartGeometry($daily),
            'sourceBreakdown' => $sourceBreakdown,
            'maxSourceCount' => $maxSourceCount,
            'perProduct' => $perProduct,
            'totalViews' => array_sum($daily['views']),
            'totalAppearances' => array_sum($daily['appearances']),
            'days' => self::DAYS,
        ])->layout('layouts.seller', [
            'title' => __('sellers.analytics.title'),
            'breadcrumb' => [
                ['label' => __('sellers.analytics.title')],
            ],
        ]);
    }
}
