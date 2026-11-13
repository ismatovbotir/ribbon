<?php

namespace App\Mcp\Tools;

use App\Models\Seller;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Approved sellers whose subscription ends within N days, is in the grace period, or has lapsed (paid products hidden). Who to call for renewal.')]
class ListSubscriptionsDueTool extends RibbonTool
{
    protected function run(Request $request, User $admin): Response
    {
        $days = max(0, min(90, (int) $request->get('days', 14)));
        $service = app(SubscriptionService::class);

        $sellers = Seller::where('status', 'approved')
            ->whereNotNull('subscription_paid_until')
            ->where('subscription_paid_until', '<=', today()->addDays($days))
            ->orderBy('subscription_paid_until')
            ->limit(self::limit($request, 50))
            ->get();

        return Response::json($sellers->map(function (Seller $s) use ($service) {
            $sub = $service->statusFor($s);

            return [
                'id' => $s->id,
                'name' => $s->name,
                'phone' => $s->phone,
                'status' => $sub['status'],
                'paid_until' => $sub['paid_until']?->toDateString(),
                'days_left' => $sub['days_left'],
                'grace_ends_at' => $sub['grace_ends_at']?->toDateString(),
                'hidden_products' => $sub['hidden_products_count'],
            ];
        })->all());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('Include subscriptions ending within this many days (default 14).'),
            'limit' => $schema->integer()->description('Max rows, 1–100 (default 50).'),
        ];
    }
}
