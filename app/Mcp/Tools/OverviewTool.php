<?php

namespace App\Mcp\Tools;

use App\Models\BotUnanswered;
use App\Models\ContactMessage;
use App\Models\OfferRequest;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerContactEvent;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Dashboard of what needs attention on Ribbon: pending sellers and products, recent offer requests, phone reveals and chat leads, open bot questions, and subscriptions ending soon. Start here.')]
class OverviewTool extends RibbonTool
{
    protected function run(Request $request, User $admin): Response
    {
        $days = max(1, min(365, (int) $request->get('days', 7)));
        $since = now()->subDays($days);
        $graceDays = app(SubscriptionService::class)->graceDays();

        return Response::json([
            'period_days' => $days,
            'sellers' => [
                'pending_review' => Seller::where('status', 'pending')->count(),
                'approved' => Seller::where('status', 'approved')->count(),
                'paid_ending_in_7_days' => Seller::where('status', 'approved')->whereBetween('subscription_paid_until', [today(), today()->addDays(7)])->count(),
                'in_grace' => Seller::where('status', 'approved')->whereBetween('subscription_paid_until', [today()->subDays($graceDays), today()->subDay()])->count(),
            ],
            'products' => [
                'pending_review' => Product::where('status', 'pending')->count(),
                'live_on_storefront' => Product::visibleOnStorefront()->count(),
            ],
            'leads' => [
                'offer_requests' => OfferRequest::where('created_at', '>=', $since)->count(),
                'phone_reveals' => SellerContactEvent::where('occurred_at', '>=', $since)->count(),
                'chat_leads_new' => ContactMessage::where('status', 'new')->count(),
            ],
            'bot' => [
                'open_unanswered_questions' => BotUnanswered::open()->count(),
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('Period for lead counts, in days (default 7).'),
        ];
    }
}
