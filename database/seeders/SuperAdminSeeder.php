<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = config('ribbon.super_admin.email');
        $password = config('ribbon.super_admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD are not set in .env — skipping Super Admin seeding.');

            return;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Super Admin', 'password' => Hash::make($password)],
        );

        $roleId = DB::table('roles')->where('type', 'admin')->where('is_super_admin', true)->value('id');

        $alreadyAssigned = DB::table('role_user')
            ->where('role_id', $roleId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $alreadyAssigned) {
            DB::table('role_user')->insert([
                'role_id' => $roleId,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
