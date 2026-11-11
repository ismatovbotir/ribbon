<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Admin permission slugs checked by the `admin-permission` gate (see
     * App\Livewire\Concerns\AuthorizesAdmin and each Admin component's
     * adminAccess()). Super Admin passes every check without these rows;
     * any other admin role (e.g. the upcoming Moderator) only gets what is
     * linked to it in permission_role. Reference data, idempotent.
     */
    private const PERMISSIONS = [
        'sellers.moderate' => 'Moderate sellers',
        'products.moderate' => 'Moderate products',
        'catalog.manage' => 'Manage categories, brands and geography',
        'content.manage' => 'Manage banners and articles',
        'pages.manage' => 'Manage CMS pages (legal pages stay Super Admin only)',
        'bot.manage' => 'Teach and switch on the rule-based chat bot',
    ];

    /**
     * Moderator (CLAUDE.md "Roles"): moderates sellers, products and content,
     * and manages the catalog. Everything else (buyer PII / offers, Telegram
     * inbox, settings, platform analytics, subscriptions, ranking, staff)
     * stays Super Admin only, because those checks use isSuperAdmin() rather
     * than a permission. Legal CMS pages also stay Super Admin only.
     */
    private const MODERATOR_PERMISSIONS = [
        'sellers.moderate', 'products.moderate', 'catalog.manage', 'content.manage', 'pages.manage', 'bot.manage',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $slug => $name) {
            Permission::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        $moderator = Role::firstOrCreate(
            ['type' => 'admin', 'slug' => 'moderator'],
            ['name' => 'Moderator', 'is_super_admin' => false],
        );

        $moderator->permissions()->syncWithoutDetaching(
            Permission::whereIn('slug', self::MODERATOR_PERMISSIONS)->pluck('id')
        );
    }
}
