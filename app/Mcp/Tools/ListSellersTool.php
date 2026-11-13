<?php

namespace App\Mcp\Tools;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List sellers (companies), newest first. Filter by status and search by company name, INN or phone.')]
class ListSellersTool extends RibbonTool
{
    protected function run(Request $request, User $admin): Response
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,suspended'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $sellers = Seller::query()
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")->orWhere('vat_number', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->withCount('products')
            ->latest()
            ->limit(self::limit($request))
            ->get();

        return Response::json($sellers->map(fn (Seller $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'inn' => $s->vat_number,
            'phone' => $s->phone,
            'status' => $s->status,
            'paid_until' => $s->subscription_paid_until?->toDateString(),
            'products' => $s->products_count,
            'registered_at' => $s->created_at?->toDateTimeString(),
        ])->all());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['pending', 'approved', 'rejected', 'suspended'])->description('Only sellers with this status.'),
            'search' => $schema->string()->description('Part of the company name, INN or phone.'),
            'limit' => $schema->integer()->description('Max rows, 1–100 (default 20).'),
        ];
    }
}
