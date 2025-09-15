<?php

namespace Database\Seeders;

use App\Models\LoginUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LoginUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test users for different roles
        LoginUser::create([
            'username' => 'superadmin',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'photo_url' => 'https://example.com/photos/superadmin.jpg',
            'employee_id' => 'EMP001',
            'phone' => '+628123456789',
            'email' => 'superadmin@syncflow.com',
            'position' => 'manager',
            'department' => 'IT',
        ]);

        LoginUser::create([
            'username' => 'admin',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'photo_url' => 'https://example.com/photos/admin.jpg',
            'employee_id' => 'EMP002',
            'phone' => '+628123456788',
            'email' => 'admin@syncflow.com',
            'position' => 'supervisor',
            'department' => 'IT',
        ]);

        LoginUser::create([
            'username' => 'operator',
            'password' => Hash::make('password123'),
            'role' => 'operator',
            'photo_url' => 'https://example.com/photos/operator.jpg',
            'employee_id' => 'EMP003',
            'phone' => '+628123456787',
            'email' => 'operator@syncflow.com',
            'position' => 'staff',
            'department' => 'Operations',
        ]);

        LoginUser::create([
            'username' => 'wit urrohman',
            'password' => Hash::make('password123'),
            'role' => 'operator',
            'photo_url' => 'https://example.com/photos/pixiee.jpg',
            'employee_id' => '101233948893',
            'phone' => '+628123456789',
            'email' => 'salwit0109@gmail.com',
            'position' => 'manager',
            'department' => 'IT',
        ]);
    }
}
