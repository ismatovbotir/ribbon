<?php

namespace App\Mcp\Tools;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List products, newest first. Filter by moderation status or seller. Shows title, seller, category, prices and whether it can be approved.')]
class ListProductsTool extends RibbonTool
{
    protected function run(Request $request, User $admin): Response
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,suspended'],
            'seller_id' => ['nullable', 'integer'],
        ]);

        $products = Product::query()
            ->with(['seller:id,name', 'category:id,name', 'prices'])
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['seller_id'] ?? null, fn ($q, $id) => $q->where('seller_id', $id))
            ->latest()
            ->limit(self::limit($request))
            ->get();

        return Response::json($products->map(fn (Product $p) => [
            'id' => $p->id,
            'title' => $p->localizedName('ru'),
            'seller' => ['id' => $p->seller?->id, 'name' => $p->seller?->name],
            'category' => $p->category?->name['ru'] ?? null,
            'status' => $p->status,
            'rejection_reason' => $p->rejection_reason,
            'prices_uzs' => $p->prices->mapWithKeys(fn ($price) => [$price->unit => $price->price])->all(),
            'can_be_approved' => $p->hasCompletePricing(),
            'created_at' => $p->created_at?->toDateTimeString(),
            'admin_url' => route('admin.products.show', $p),
        ])->all());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['pending', 'approved', 'rejected', 'suspended'])->description('Only products with this status.'),
            'seller_id' => $schema->integer()->description('Only this seller\'s products.'),
            'limit' => $schema->integer()->description('Max rows, 1–100 (default 20).'),
        ];
    }
}
