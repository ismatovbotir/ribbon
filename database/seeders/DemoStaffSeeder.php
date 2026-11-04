<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo logins for every staff actor, so local QA never needs the real Super
 * Admin password from .env: admin@ / moderator@ / employee@demo.ribbon.uz,
 * all with the demo password (same as the demo sellers). The three demo
 * sellers from DemoCatalogSeeder already cover the paid / grace / lapsed
 * subscription states. Local only, idempotent.
 */
class DemoStaffSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        if (! app()->isLocal()) {
            $this->command?->warn('DemoStaffSeeder only runs in the local environment — skipped.');

            return;
        }

        $this->account('admin@demo.ribbon.uz', 'Demo Super Admin')->roles()->syncWithoutDetaching(
            Role::where('type', 'admin')->where('is_super_admin', true)->pluck('id')
        );

        $this->account('moderator@demo.ribbon.uz', 'Demo Moderator')->roles()->syncWithoutDetaching(
            Role::where('type', 'admin')->where('slug', 'moderator')->pluck('id')
        );

        // Employee of the first demo seller (paid subscription).
        $seller = Seller::whereHas('users', fn ($q) => $q->where('email', 'seller1@demo.ribbon.uz'))->first();

        if ($seller) {
            $this->account('employee@demo.ribbon.uz', 'Demo Employee');
            $seller->users()->syncWithoutDetaching([
                User::where('email', 'employee@demo.ribbon.uz')->value('id') => [
                    'role_id' => Role::where('type', 'seller')->where('slug', 'employee')->value('id'),
                ],
            ]);
        }
    }

    private function account(string $email, string $name): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->name ??= $name;
        $user->password = Hash::make(self::PASSWORD);
        $user->save();

        return $user;
    }
}
