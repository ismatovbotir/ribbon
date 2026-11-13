<?php

namespace App\Mcp\Tools;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Approve, reject or suspend a product listing, only when the owner explicitly asks. A product needs a price on every unit to be approved. Reject and suspend need a reason the seller will see.')]
class ModerateProductTool extends RibbonTool
{
    protected function run(Request $request, User $admin): Response
    {
        $data = $request->validate([
            'product_id' => ['required', 'string', 'max:40'],
            'decision' => ['required', 'in:approve,reject,suspend'],
            'reason' => ['required_unless:decision,approve', 'nullable', 'string', 'max:1000'],
        ]);

        $product = Product::find($data['product_id']);

        if (! $product) {
            return Response::error('No product with id '.$data['product_id'].'.');
        }

        if ($data['decision'] === 'approve' && ! $product->hasCompletePricing()) {
            return Response::error('This product cannot be approved: every enabled unit needs a price above zero.');
        }

        if ($data['decision'] === 'suspend' && $product->status !== 'approved') {
            return Response::error('Only an approved product can be suspended (this one is '.$product->status.'). Use reject instead.');
        }

        $from = $product->status;

        match ($data['decision']) {
            'approve' => $product->approve($admin),
            'reject' => $product->reject($admin, $data['reason']),
            'suspend' => $product->suspend($admin, $data['reason']),
        };

        $this->audit($admin, 'product-'.$data['decision'], ['product_id' => $product->id, 'from' => $from]);

        return Response::text('Product "'.$product->localizedName('ru')."\" ({$product->id}): {$from} → {$product->status}.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->string()->required()->description('Product id (from list-products).'),
            'decision' => $schema->string()->enum(['approve', 'reject', 'suspend'])->required(),
            'reason' => $schema->string()->description('Required for reject and suspend; shown to the seller.'),
        ];
    }
}
