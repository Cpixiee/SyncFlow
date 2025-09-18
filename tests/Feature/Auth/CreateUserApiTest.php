<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\LoginUser;

class CreateUserApiTest extends TestCase
{
    /** @test */
    public function only_superadmin_can_create_users()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'role' => 'admin',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'newuser@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiSuccess($response, 'User created successfully');
        
        $this->assertDatabaseHas('login_users', [
            'username' => 'newuser',
            'role' => 'admin',
            'employee_id' => 'EMP123',
            'email' => 'newuser@example.com',
        ]);
    }

    /** @test */
    public function admin_cannot_create_users()
    {
        $admin = LoginUser::factory()->admin()->create();

        $response = $this->actingAsUser($admin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'role' => 'admin',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'newuser@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiError($response, 403);
        
        $this->assertDatabaseMissing('login_users', [
            'username' => 'newuser',
        ]);
    }

    /** @test */
    public function operator_cannot_create_users()
    {
        $operator = LoginUser::factory()->operator()->create();

        $response = $this->actingAsUser($operator)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'role' => 'admin',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'newuser@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiError($response, 403);
        
        $this->assertDatabaseMissing('login_users', [
            'username' => 'newuser',
        ]);
    }

    /** @test */
    public function unauthenticated_users_cannot_create_users()
    {
        $response = $this->postJson('/api/v1/create-user', [
            'username' => 'newuser',
            'role' => 'admin',
            'employee_id' => 'EMP123',
            'phone' => '+628123456789',
            'email' => 'newuser@example.com',
            'position' => 'staff',
            'department' => 'IT',
        ]);

        $this->assertApiError($response, 401);
        
        $this->assertDatabaseMissing('login_users', [
            'username' => 'newuser',
        ]);
    }

    /** @test */
    public function it_creates_user_with_default_password_when_not_provided()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'role' => 'admin',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'newuser@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiSuccess($response, 'User created successfully');
        
        $data = $response->json()['data'];
        $this->assertEquals('admin#1234', $data['default_password']);
        $this->assertTrue($data['must_change_password']);
        
        // Verify user can login with default password
        $loginResponse = $this->postJson('/api/v1/login', [
            'username' => 'newuser',
            'password' => 'admin#1234',
        ]);
        
        $this->assertApiSuccess($loginResponse, 'Login successful');
    }

    /** @test */
    public function it_creates_user_with_custom_password_when_provided()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'password' => 'custompass123',
                'role' => 'admin',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'newuser@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiSuccess($response, 'User created successfully');
        
        $data = $response->json()['data'];
        $this->assertNull($data['default_password']);
        $this->assertTrue($data['must_change_password']);
        
        // Verify user can login with custom password
        $loginResponse = $this->postJson('/api/v1/login', [
            'username' => 'newuser',
            'password' => 'custompass123',
        ]);
        
        $this->assertApiSuccess($loginResponse, 'Login successful');
    }

    /** @test */
    public function it_creates_user_with_must_change_password_true_by_default()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'role' => 'admin',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'newuser@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiSuccess($response, 'User created successfully');
        
        $data = $response->json()['data'];
        $this->assertTrue($data['must_change_password']);
        
        $user = LoginUser::where('username', 'newuser')->first();
        $this->assertFalse($user->password_changed);
        $this->assertNull($user->password_changed_at);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', []);

        $this->assertApiError($response, 400, 'Request invalid');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('username', $errors);
        $this->assertArrayHasKey('role', $errors);
        $this->assertArrayHasKey('employee_id', $errors);
        $this->assertArrayHasKey('phone', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('position', $errors);
        $this->assertArrayHasKey('department', $errors);
    }

    /** @test */
    public function it_validates_unique_username()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();
        $existingUser = LoginUser::factory()->create(['username' => 'existing']);

        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'existing',
                'role' => 'admin',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'new@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiError($response, 400, 'Request invalid');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('username', $errors);
    }

    /** @test */
    public function it_validates_unique_employee_id()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();
        $existingUser = LoginUser::factory()->create(['employee_id' => 'EMP123']);

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

        $this->assertApiError($response, 400, 'Request invalid');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('employee_id', $errors);
    }

    /** @test */
    public function it_validates_unique_email()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();
        $existingUser = LoginUser::factory()->create(['email' => 'existing@example.com']);

        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'role' => 'admin',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'existing@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiError($response, 400, 'Request invalid');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('email', $errors);
    }

    /** @test */
    public function it_validates_role_enum_values()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'role' => 'invalid_role',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'new@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiError($response, 400, 'Request invalid');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('role', $errors);
    }

    /** @test */
    public function it_validates_position_enum_values()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'role' => 'admin',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'new@example.com',
                'position' => 'invalid_position',
                'department' => 'IT',
            ]);

        $this->assertApiError($response, 400, 'Request invalid');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('position', $errors);
    }

    /** @test */
    public function it_can_create_all_valid_roles()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();
        $roles = ['operator', 'admin', 'superadmin'];

        foreach ($roles as $index => $role) {
            $response = $this->actingAsUser($superadmin)
                ->postJson('/api/v1/create-user', [
                    'username' => "user_$role",
                    'role' => $role,
                    'employee_id' => "EMP$index",
                    'phone' => "+62812345678$index",
                    'email' => "$role@example.com",
                    'position' => 'staff',
                    'department' => 'IT',
                ]);

            $this->assertApiSuccess($response, 'User created successfully');
            
            $data = $response->json()['data'];
            $this->assertEquals($role, $data['role']);
        }
    }

    /** @test */
    public function it_can_create_all_valid_positions()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();
        $positions = ['manager', 'staff', 'supervisor'];

        foreach ($positions as $index => $position) {
            $response = $this->actingAsUser($superadmin)
                ->postJson('/api/v1/create-user', [
                    'username' => "user_$position",
                    'role' => 'admin',
                    'employee_id' => "EMP_$position",
                    'phone' => "+62812345678$index",
                    'email' => "$position@example.com",
                    'position' => $position,
                    'department' => 'IT',
                ]);

            $this->assertApiSuccess($response, 'User created successfully');
            
            $data = $response->json()['data'];
            $this->assertEquals($position, $data['position']);
        }
    }

    /** @test */
    public function it_validates_password_minimum_length_when_provided()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'password' => '123', // Too short
                'role' => 'admin',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'new@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiError($response, 400, 'Request invalid');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('password', $errors);
    }

    /** @test */
    public function it_validates_email_format()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'role' => 'admin',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'invalid_email',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiError($response, 400, 'Request invalid');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('email', $errors);
    }

    /** @test */
    public function it_returns_complete_user_data_in_response()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'fulluser',
                'role' => 'admin',
                'photo_url' => 'https://example.com/photo.jpg',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'full@example.com',
                'position' => 'manager',
                'department' => 'Engineering',
            ]);

        $this->assertApiSuccess($response, 'User created successfully');
        
        $data = $response->json()['data'];
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('username', $data);
        $this->assertArrayHasKey('role', $data);
        $this->assertArrayHasKey('photo_url', $data);
        $this->assertArrayHasKey('employee_id', $data);
        $this->assertArrayHasKey('phone', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('position', $data);
        $this->assertArrayHasKey('department', $data);
        $this->assertArrayHasKey('must_change_password', $data);
        $this->assertArrayHasKey('default_password', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
        
        // Check that password is not exposed
        $this->assertArrayNotHasKey('password', $data);
        
        $this->assertEquals('fulluser', $data['username']);
        $this->assertEquals('admin', $data['role']);
        $this->assertEquals('https://example.com/photo.jpg', $data['photo_url']);
        $this->assertEquals('EMP123', $data['employee_id']);
        $this->assertEquals('+628123456789', $data['phone']);
        $this->assertEquals('full@example.com', $data['email']);
        $this->assertEquals('manager', $data['position']);
        $this->assertEquals('Engineering', $data['department']);
    }
}

