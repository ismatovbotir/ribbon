<?php

namespace App\Mcp\Tools;

use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Bot\BotText;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List questions buyers left through the website consultant / contact form, newest first. Phones, emails, Telegram and WhatsApp handles are masked; use reveal-buyer-contact for one lead.')]
class ListChatLeadsTool extends RibbonTool
{
    protected function run(Request $request, User $admin): Response
    {
        $data = $request->validate(['status' => ['nullable', 'in:new,handled']]);

        $leads = ContactMessage::query()
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->limit(self::limit($request))
            ->get();

        return Response::json($leads->map(fn (ContactMessage $m) => [
            'id' => $m->id,
            'created_at' => $m->created_at?->toDateTimeString(),
            'source' => $m->source,
            'phone' => self::maskPhone($m->phone),
            'message' => BotText::mask((string) $m->message), // buyers type numbers and @handles into the text too
            'page' => $m->page,
            'language' => $m->locale,
            'status' => $m->status,
        ])->all());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['new', 'handled'])->description('Only leads with this status.'),
            'limit' => $schema->integer()->description('Max rows, 1–100 (default 20).'),
        ];
    }
}
