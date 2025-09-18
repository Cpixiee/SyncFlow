<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed SuperAdmin for API authentication
        $this->call(SuperAdminSeeder::class);
        
        // Seed additional test users
        $this->call(LoginUserSeeder::class);
        
        // Only create test users in development environment
        if (app()->environment(['local', 'testing'])) {
            // User::factory(10)->create();

            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
