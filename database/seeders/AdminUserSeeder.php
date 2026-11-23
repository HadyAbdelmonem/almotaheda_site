<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo admin user: admin@example.com with a RANDOM password that is
 * printed to the console exactly once (spec §5). The owner must change
 * it immediately. Never used by ProductionSeeder.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Str::random(16);

        // is_admin is not mass-assignable (Phase 11 hardening): set it
        // explicitly via forceFill in this trusted seeder only.
        $admin = User::firstOrNew(['email' => 'admin@example.com']);
        $admin->forceFill(['name' => 'Admin', 'password' => $password, 'is_admin' => true])->save();

        $this->command?->warn("Admin user created: admin@example.com / {$password} — change this password immediately.");
    }
}
