<?php

namespace App\Mcp\Tools;

use App\Models\OfferRequest;
use App\Models\OfferRequestItem;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List buyer offer requests (leads), newest first: items, sellers, quantities, seller outcomes and source (UTM). Buyer phone and email are masked; use reveal-buyer-contact for one request when the owner needs to call.')]
class ListOfferRequestsTool extends RibbonTool
{
    protected function run(Request $request, User $admin): Response
    {
        $days = max(1, min(365, (int) $request->get('days', 30)));

        $requests = OfferRequest::query()
            ->with(['items.product', 'items.seller:id,name', 'outcomes'])
            ->where('created_at', '>=', now()->subDays($days))
            ->latest()
            ->limit(self::limit($request))
            ->get();

        return Response::json($requests->map(fn (OfferRequest $r) => [
            'id' => $r->id,
            'created_at' => $r->created_at?->toDateTimeString(),
            'buyer' => [
                'phone' => self::maskPhone($r->phone),
                'email' => self::maskEmail($r->email),
                'company' => $r->company_name,
                'type' => $r->buyer_type,
            ],
            'items' => $r->items->map(fn (OfferRequestItem $i) => [
                'product' => $i->product?->localizedName('ru'),
                'seller' => $i->seller?->name,
                'quantity' => $i->quantity.' '.$i->unit,
                'price_at_request_uzs' => $i->price_at_request,
            ])->all(),
            'outcomes' => $r->outcomes->map(fn ($o) => ['seller_id' => $o->seller_id, 'outcome' => $o->outcome])->all(),
            'source' => $r->utm ?: ($r->referrer ? ['referrer' => $r->referrer] : 'direct'),
            'admin_url' => route('admin.offers.show', $r),
        ])->all());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('Look back this many days (default 30).'),
            'limit' => $schema->integer()->description('Max rows, 1–100 (default 20).'),
        ];
    }
}
