<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['admin', 'seller']);
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_super_admin')->default(false);
            $table->timestamps();

            $table->unique(['type', 'slug']);
        });

        // Seller roles are a fixed, closed set (Owner/Employee) that
        // seller_user FKs against — seed them here rather than in a seeder
        // since the app can't function without them. Buyers never register
        // (browse-only, request a Commercial Offer or call the seller), so
        // there is no buyer-type role. Super Admin is likewise seeded here
        // (SuperAdminSeeder assigns it to the one default admin user); other
        // admin roles are dynamic and created through the admin UI instead.
        DB::table('roles')->insert([
            ['type' => 'seller', 'name' => 'Owner', 'slug' => 'owner', 'is_super_admin' => false, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'seller', 'name' => 'Employee', 'slug' => 'employee', 'is_super_admin' => false, 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'admin', 'name' => 'Super Admin', 'slug' => 'super-admin', 'is_super_admin' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
