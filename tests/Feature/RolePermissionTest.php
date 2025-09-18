<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\LoginUser;

class RolePermissionTest extends TestCase
{
    /**
     * Test that operator role has correct permissions
     * - Can login
     * - Can access authenticated endpoints (me, logout, refresh, change-password)
     * - Cannot create users
     * - Cannot access admin/superadmin specific endpoints
     */
    
    /** @test */
    public function operator_can_login_and_access_basic_endpoints()
    {
        $operator = LoginUser::factory()->operator()->create([
            'username' => 'operator1',
            'password' => bcrypt('password123'),
        ]);

        // Can login
        $loginResponse = $this->postJson('/api/v1/login', [
            'username' => 'operator1',
            'password' => 'password123',
        ]);
        $this->assertApiSuccess($loginResponse, 'Login successful');
        
        $loginData = $loginResponse->json()['data'];
        $this->assertEquals('operator', $loginData['role']);

        // Can access profile
        $profileResponse = $this->actingAsUser($operator)->getJson('/api/v1/me');
        $this->assertApiSuccess($profileResponse);

        // Can logout
        $logoutResponse = $this->actingAsUser($operator)->postJson('/api/v1/logout');
        $this->assertApiSuccess($logoutResponse, 'Successfully logged out');

        // Can refresh token
        $refreshResponse = $this->actingAsUser($operator)->postJson('/api/v1/refresh');
        $this->assertApiSuccess($refreshResponse, 'Token refreshed successfully');

        // Can change password
        $passwordResponse = $this->actingAsUser($operator)->postJson('/api/v1/change-password', [
            'current_password' => 'password123',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);
        $this->assertApiSuccess($passwordResponse, 'Password changed successfully');
    }

    /** @test */
    public function operator_cannot_create_users()
    {
        $operator = LoginUser::factory()->operator()->create();

        $response = $this->actingAsUser($operator)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'role' => 'operator',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'new@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiError($response, 403);
        $this->assertDatabaseMissing('login_users', ['username' => 'newuser']);
    }

    /**
     * Test that admin role has correct permissions
     * - Can login
     * - Can access authenticated endpoints
     * - Can perform CRUD operations (not fully implemented in current API, but has permission structure)
     * - Cannot create users (only superadmin can)
     */
    
    /** @test */
    public function admin_can_login_and_access_basic_endpoints()
    {
        $admin = LoginUser::factory()->admin()->create([
            'username' => 'admin1',
            'password' => bcrypt('password123'),
        ]);

        // Can login
        $loginResponse = $this->postJson('/api/v1/login', [
            'username' => 'admin1',
            'password' => 'password123',
        ]);
        $this->assertApiSuccess($loginResponse, 'Login successful');
        
        $loginData = $loginResponse->json()['data'];
        $this->assertEquals('admin', $loginData['role']);

        // Can access all authenticated endpoints
        $profileResponse = $this->actingAsUser($admin)->getJson('/api/v1/me');
        $this->assertApiSuccess($profileResponse);

        $logoutResponse = $this->actingAsUser($admin)->postJson('/api/v1/logout');
        $this->assertApiSuccess($logoutResponse, 'Successfully logged out');

        $refreshResponse = $this->actingAsUser($admin)->postJson('/api/v1/refresh');
        $this->assertApiSuccess($refreshResponse, 'Token refreshed successfully');

        $passwordResponse = $this->actingAsUser($admin)->postJson('/api/v1/change-password', [
            'current_password' => 'password123',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);
        $this->assertApiSuccess($passwordResponse, 'Password changed successfully');
    }

    /** @test */
    public function admin_cannot_create_users()
    {
        $admin = LoginUser::factory()->admin()->create();

        $response = $this->actingAsUser($admin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'role' => 'operator',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'new@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiError($response, 403);
        $this->assertDatabaseMissing('login_users', ['username' => 'newuser']);
    }

    /**
     * Test that superadmin role has correct permissions
     * - Can login
     * - Can access authenticated endpoints
     * - Can perform CRUD operations
     * - Can create users
     */
    
    /** @test */
    public function superadmin_can_login_and_access_all_endpoints()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        // Can login
        $loginResponse = $this->postJson('/api/v1/login', [
            'username' => 'superadmin',
            'password' => 'admin123',
        ]);
        $this->assertApiSuccess($loginResponse, 'Login successful');
        
        $loginData = $loginResponse->json()['data'];
        $this->assertEquals('superadmin', $loginData['role']);

        // Can access all authenticated endpoints
        $profileResponse = $this->actingAsUser($superadmin)->getJson('/api/v1/me');
        $this->assertApiSuccess($profileResponse);

        $refreshResponse = $this->actingAsUser($superadmin)->postJson('/api/v1/refresh');
        $this->assertApiSuccess($refreshResponse, 'Token refreshed successfully');

        $passwordResponse = $this->actingAsUser($superadmin)->postJson('/api/v1/change-password', [
            'current_password' => 'admin123',
            'new_password' => 'newadminpass123',
            'new_password_confirmation' => 'newadminpass123',
        ]);
        $this->assertApiSuccess($passwordResponse, 'Password changed successfully');
    }

    /** @test */
    public function superadmin_can_create_users()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'role' => 'admin',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'new@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiSuccess($response, 'User created successfully');
        $this->assertDatabaseHas('login_users', [
            'username' => 'newuser',
            'role' => 'admin',
            'employee_id' => 'EMP123',
        ]);
    }

    /** @test */
    public function superadmin_can_create_all_role_types()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();
        $roles = ['operator', 'admin', 'superadmin'];

        foreach ($roles as $index => $role) {
            $response = $this->actingAsUser($superadmin)
                ->postJson('/api/v1/create-user', [
                    'username' => "new_$role",
                    'role' => $role,
                    'employee_id' => "EMP_$role",
                    'phone' => "+62812345678$index",
                    'email' => "$role@example.com",
                    'position' => 'staff',
                    'department' => 'IT',
                ]);

            $this->assertApiSuccess($response, 'User created successfully');
            
            $data = $response->json()['data'];
            $this->assertEquals($role, $data['role']);
            
            $this->assertDatabaseHas('login_users', [
                'username' => "new_$role",
                'role' => $role,
            ]);
        }
    }

    /** @test */
    public function role_hierarchy_is_correctly_enforced()
    {
        $operator = LoginUser::factory()->operator()->create();
        $admin = LoginUser::factory()->admin()->create();
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        // Test create-user endpoint (only superadmin allowed)
        $createUserData = [
            'username' => 'testuser',
            'role' => 'operator',
            'employee_id' => 'EMP999',
            'phone' => '+628123456789',
            'email' => 'test@example.com',
            'position' => 'staff',
            'department' => 'IT',
        ];

        // Operator cannot create
        $operatorResponse = $this->actingAsUser($operator)
            ->postJson('/api/v1/create-user', $createUserData);
        $this->assertApiError($operatorResponse, 403);

        // Admin cannot create
        $adminResponse = $this->actingAsUser($admin)
            ->postJson('/api/v1/create-user', $createUserData);
        $this->assertApiError($adminResponse, 403);

        // Superadmin can create
        $superadminResponse = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', $createUserData);
        $this->assertApiSuccess($superadminResponse, 'User created successfully');
    }

    /** @test */
    public function all_roles_can_manage_their_own_password()
    {
        $users = [
            LoginUser::factory()->operator()->create(['password' => bcrypt('password123')]),
            LoginUser::factory()->admin()->create(['password' => bcrypt('password123')]),
            LoginUser::where('username', 'superadmin')->first(),
        ];

        foreach ($users as $user) {
            $currentPassword = $user->username === 'superadmin' ? 'admin123' : 'password123';
            
            $response = $this->actingAsUser($user)
                ->postJson('/api/v1/change-password', [
                    'current_password' => $currentPassword,
                    'new_password' => 'newpassword123',
                    'new_password_confirmation' => 'newpassword123',
                ]);

            $this->assertApiSuccess($response, 'Password changed successfully');
            
            $data = $response->json()['data'];
            $this->assertEquals($user->role, $data['role']);
            $this->assertFalse($data['must_change_password']);
        }
    }

    /** @test */
    public function all_roles_can_access_their_profile()
    {
        $users = [
            LoginUser::factory()->operator()->create(),
            LoginUser::factory()->admin()->create(),
            LoginUser::where('username', 'superadmin')->first(),
        ];

        foreach ($users as $user) {
            $response = $this->actingAsUser($user)->getJson('/api/v1/me');

            $this->assertApiSuccess($response);
            
            $data = $response->json()['data'];
            $this->assertEquals($user->id, $data['id']);
            $this->assertEquals($user->role, $data['role']);
            $this->assertEquals($user->username, $data['username']);
        }
    }

    /** @test */
    public function middleware_correctly_blocks_unauthorized_access()
    {
        // Test without authentication
        $response = $this->postJson('/api/v1/create-user', [
            'username' => 'test',
            'role' => 'operator',
            'employee_id' => 'EMP001',
            'phone' => '+628123456789',
            'email' => 'test@example.com',
            'position' => 'staff',
            'department' => 'IT',
        ]);

        $this->assertApiError($response, 401);

        // Test with invalid token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid.token.here',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/create-user', [
            'username' => 'test',
            'role' => 'operator',
            'employee_id' => 'EMP001',
            'phone' => '+628123456789',
            'email' => 'test@example.com',
            'position' => 'staff',
            'department' => 'IT',
        ]);

        $this->assertApiError($response, 401);
    }
}

