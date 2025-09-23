<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create an admin user if one doesn't already exist
        $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
        $adminPassword = env('ADMIN_PASSWORD', 'password');

        $user = User::where('email', $adminEmail)->first();
        if (! $user) {
            User::create([
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
            ]);
        } else {
            // Ensure role is admin
            if ($user->role !== 'admin') {
                $user->role = 'admin';
                $user->save();
            }
        }
    }
}
