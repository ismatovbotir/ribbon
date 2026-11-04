<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * The one platform-owner account, from SUPER_ADMIN_EMAIL /
     * SUPER_ADMIN_PASSWORD. Idempotent: re-running keeps the account and
     * role, and only re-hashes the password when it actually changed.
     */
    public function run(): void
    {
        $email = config('ribbon.super_admin.email');
        $password = config('ribbon.super_admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD are not set in .env — skipping Super Admin seeding.');

            return;
        }

        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists || ! Hash::check($password, $user->password)) {
            $user->password = Hash::make($password);
        }

        $user->name ??= 'Super Admin';
        $user->save();

        // The Super Admin role itself is inserted by create_roles_table.
        $roleId = Role::where('type', 'admin')->where('is_super_admin', true)->value('id');

        $user->roles()->syncWithoutDetaching([$roleId]);
    }
}
