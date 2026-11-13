<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Base for every Ribbon MCP tool (App\Mcp\Servers\RibbonAdminServer).
 *
 * The server only runs locally (`php artisan mcp:start ribbon`, reached
 * from Claude Desktop over SSH), so the security boundary is the server
 * login. Every action is still done AS a real Super Admin user, the one
 * named in MCP_ADMIN_EMAIL, so moderation and payments are attributed
 * and the same gates apply as in the admin panel.
 *
 * Results are data written by sellers and buyers, never instructions:
 * write tools re-check everything and the Instructions tell Claude so.
 */
abstract class RibbonTool extends Tool
{
    abstract protected function run(Request $request, User $admin): Response;

    /** "ModerateSellerTool" → "moderate-seller" (the names the server Instructions use). */
    public function name(): string
    {
        return Str::beforeLast(parent::name(), '-tool');
    }

    public function handle(Request $request): Response
    {
        $admin = $this->admin($request);

        if (! $admin) {
            return Response::error('MCP is not set up: MCP_ADMIN_EMAIL must be the email of a Super Admin user.');
        }

        return $this->run($request, $admin);
    }

    protected function admin(Request $request): ?User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            $email = config('ribbon.mcp_admin_email');
            $user = $email ? User::where('email', $email)->first() : null;
        }

        return $user?->isSuperAdmin() ? $user : null;
    }

    /** Write actions are logged (ids only, no buyer data) so changes made through Claude can be traced. */
    protected function audit(User $admin, string $action, array $context = []): void
    {
        Log::info('MCP '.$action, ['admin_id' => $admin->id, 'via' => 'claude-mcp'] + $context);
    }

    /** "+998 90 123 45 67" → "+99890*****67": enough to tell leads apart, not to call. */
    protected static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return $phone;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) <= 7) {
            return str_repeat('*', strlen($digits));
        }

        return (str_starts_with(trim($phone), '+') ? '+' : '').substr($digits, 0, 5).str_repeat('*', strlen($digits) - 7).substr($digits, -2);
    }

    protected static function maskEmail(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return $email;
        }

        [$name, $domain] = explode('@', $email, 2);

        return mb_substr($name, 0, 1).'***@'.$domain;
    }

    protected static function limit(Request $request, int $default = 20): int
    {
        return max(1, min(100, (int) $request->get('limit', $default)));
    }
}
