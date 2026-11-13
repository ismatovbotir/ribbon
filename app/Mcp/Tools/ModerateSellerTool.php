<?php

namespace App\Mcp\Tools;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Approve, reject or suspend a seller (company). Only after the owner has checked the INN and phoned the company, and only when the owner explicitly asks. Reject and suspend need a reason the seller will see.')]
class ModerateSellerTool extends RibbonTool
{
    protected function run(Request $request, User $admin): Response
    {
        $data = $request->validate([
            'seller_id' => ['required', 'integer'],
            'decision' => ['required', 'in:approve,reject,suspend'],
            'reason' => ['required_unless:decision,approve', 'nullable', 'string', 'max:1000'],
        ]);

        $seller = Seller::find($data['seller_id']);

        if (! $seller) {
            return Response::error('No seller with id '.$data['seller_id'].'.');
        }

        if ($data['decision'] === 'suspend' && $seller->status !== 'approved') {
            return Response::error('Only an approved seller can be suspended (this one is '.$seller->status.'). Use reject instead.');
        }

        $from = $seller->status;

        match ($data['decision']) {
            'approve' => $seller->approve($admin),
            'reject' => $seller->reject($admin, $data['reason']),
            'suspend' => $seller->suspend($admin, $data['reason']),
        };

        $this->audit($admin, 'seller-'.$data['decision'], ['seller_id' => $seller->id, 'from' => $from]);

        return Response::text("Seller \"{$seller->name}\" (id {$seller->id}): {$from} → {$seller->status}.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'seller_id' => $schema->integer()->required(),
            'decision' => $schema->string()->enum(['approve', 'reject', 'suspend'])->required(),
            'reason' => $schema->string()->description('Required for reject and suspend; shown to the seller.'),
        ];
    }
}
