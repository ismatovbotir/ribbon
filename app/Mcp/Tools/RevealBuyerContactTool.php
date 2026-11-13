<?php

namespace App\Mcp\Tools;

use App\Models\ContactMessage;
use App\Models\OfferRequest;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Show the full, unmasked contact of ONE buyer: an offer request or a chat lead. Use only when the owner explicitly asks to see or call that buyer. Each reveal is logged.')]
class RevealBuyerContactTool extends RibbonTool
{
    protected function run(Request $request, User $admin): Response
    {
        $data = $request->validate([
            'type' => ['required', 'in:offer_request,chat_lead'],
            'id' => ['required', 'integer'],
        ]);

        $record = $data['type'] === 'offer_request' ? OfferRequest::find($data['id']) : ContactMessage::find($data['id']);

        if (! $record) {
            return Response::error('Not found.');
        }

        $this->audit($admin, 'reveal-buyer-contact', ['type' => $data['type'], 'id' => $record->id]);

        return Response::json($data['type'] === 'offer_request'
            ? ['phone' => $record->phone, 'email' => $record->email, 'company' => $record->company_name]
            : ['phone' => $record->phone, 'message' => $record->message]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['offer_request', 'chat_lead'])->required(),
            'id' => $schema->integer()->required(),
        ];
    }
}
