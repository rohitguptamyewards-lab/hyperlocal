<?php

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the default super admin account for local development and staging.
 *
 * Run: php artisan db:seed --class=SuperAdminSeeder
 *
 * IMPORTANT: Change the password before any production deploy.
 * Credentials are intentionally plain-text here so they're easy to find in dev.
 *
 * Login: POST /api/super-admin/auth/login
 *   { "email": "admin@hyperlocal.internal", "password": "changeme123" }
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        SuperAdmin::updateOrCreate(
            ['email' => 'admin@hyperlocal.internal'],
            [
                'name'     => 'Platform Admin',
                'password' => Hash::make('changeme123'),
            ],
        );

        $this->command->info('SuperAdmin created: admin@hyperlocal.internal / changeme123');
    }
}
