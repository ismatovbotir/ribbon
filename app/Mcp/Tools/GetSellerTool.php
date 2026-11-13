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
#[Description('Full details of one seller: company and owner contacts, INN, address, status, subscription, product counts and the admin page link.')]
class GetSellerTool extends RibbonTool
{
    protected function run(Request $request, User $admin): Response
    {
        $data = $request->validate(['seller_id' => ['required', 'integer']]);
        $seller = Seller::with('city')->find($data['seller_id']);

        if (! $seller) {
            return Response::error('No seller with id '.$data['seller_id'].'.');
        }

        $sub = app(SubscriptionService::class)->statusFor($seller);
        $city = $seller->city?->name;

        return Response::json([
            'id' => $seller->id,
            'name' => $seller->name,
            'inn' => $seller->vat_number,
            'phone' => $seller->phone,
            'address' => $seller->address,
            'city' => is_array($city) ? ($city['ru'] ?? reset($city)) : $city,
            'status' => $seller->status,
            'rejected_or_suspended_reason' => $seller->rejected_reason,
            'is_vat_payer' => $seller->is_vat_payer,
            'users' => $seller->users()->get()->map(fn (User $u) => ['name' => $u->name, 'email' => $u->email])->all(),
            'subscription' => [
                'status' => $sub['status'],
                'paid_until' => $sub['paid_until']?->toDateString(),
                'days_left' => $sub['days_left'],
                'grace_ends_at' => $sub['grace_ends_at']?->toDateString(),
                'hidden_products' => $sub['hidden_products_count'],
            ],
            'products_by_status' => $seller->products()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
            'registered_at' => $seller->created_at?->toDateTimeString(),
            'admin_url' => route('admin.sellers.show', $seller),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'seller_id' => $schema->integer()->required()->description('Seller id (from list-sellers).'),
        ];
    }
}
