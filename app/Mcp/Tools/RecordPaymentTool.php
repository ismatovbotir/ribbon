<?php

namespace App\Mcp\Tools;

use App\Models\Seller;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Record a subscription payment the owner received (bank transfer, in UZS), or grant free months (amount 0, free=true). Extends paid_until by the given months. Only when the owner explicitly asks and states the amount.')]
class RecordPaymentTool extends RibbonTool
{
    protected function run(Request $request, User $admin): Response
    {
        $data = $request->validate([
            'seller_id' => ['required', 'integer'],
            'amount_uzs' => ['required', 'integer', 'min:0', 'max:100000000'],
            'months' => ['required', 'integer', 'min:1', 'max:24'],
            'free' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $seller = Seller::find($data['seller_id']);

        if (! $seller) {
            return Response::error('No seller with id '.$data['seller_id'].'.');
        }

        $free = ! empty($data['free']);

        if ($free !== ($data['amount_uzs'] === 0)) {
            return Response::error('Use amount_uzs 0 together with free=true for free months; a paid payment needs an amount above 0.');
        }

        $service = app(SubscriptionService::class);
        $note = trim(($data['note'] ?? '').' (via Claude)');
        $free
            ? $service->grantFreeMonths($seller, $admin, $data['months'], $note)
            : $service->recordPayment($seller, $admin, $data['amount_uzs'], $data['months'], $note);

        $seller->refresh();
        $this->audit($admin, $free ? 'subscription-free' : 'subscription-payment', ['seller_id' => $seller->id, 'months' => $data['months']]);

        return Response::text("Seller \"{$seller->name}\": paid until {$seller->subscription_paid_until?->toDateString()}.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'seller_id' => $schema->integer()->required(),
            'amount_uzs' => $schema->integer()->required()->description('Amount received in UZS (0 for free months).'),
            'months' => $schema->integer()->required()->description('Months to extend, 1–24.'),
            'free' => $schema->boolean()->description('true = free months (amount must be 0).'),
            'note' => $schema->string()->description('e.g. bank transfer number.'),
        ];
    }
}
