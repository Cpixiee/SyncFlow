<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\LoginUser;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;

class LoginApiTest extends TestCase
{
    #[Test]
    public function it_can_login_with_valid_credentials()
    {
        $user = LoginUser::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $this->assertApiSuccess($response, 'Login successful');
        
        $data = $response->json()['data'];
        $this->assertEquals($user->id, $data['id']);
        $this->assertEquals('testuser', $data['username']);
        $this->assertEquals('admin', $data['role']);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('token_type', $data);
        $this->assertArrayHasKey('expires_in', $data);
        $this->assertEquals('Bearer', $data['token_type']);
        
        // Check that password is not exposed
        $this->assertArrayNotHasKey('password', $data);
    }

    #[Test]
    public function it_returns_must_change_password_status_for_new_users()
    {
        $user = LoginUser::factory()->mustChangePassword()->create([
            'username' => 'newuser',
            'password' => Hash::make('admin#1234'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'newuser',
            'password' => 'admin#1234',
        ]);

        $this->assertApiSuccess($response, 'Login successful');
        
        $data = $response->json()['data'];
        $this->assertTrue($data['must_change_password']);
        $this->assertNull($data['password_changed_at']);
    }

    #[Test]
    public function it_returns_false_for_must_change_password_for_users_who_changed_password()
    {
        $user = LoginUser::factory()->passwordChanged()->create([
            'username' => 'changeduser',
            'password' => Hash::make('newpassword123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'changeduser',
            'password' => 'newpassword123',
        ]);

        $this->assertApiSuccess($response, 'Login successful');
        
        $data = $response->json()['data'];
        $this->assertFalse($data['must_change_password']);
        $this->assertNotNull($data['password_changed_at']);
    }

    #[Test]
    public function it_rejects_login_with_invalid_credentials()
    {
        $user = LoginUser::factory()->create([
            'username' => 'testuser',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $this->assertApiError($response, 401, 'Invalid username or password');
    }

    #[Test]
    public function it_rejects_login_with_nonexistent_user()
    {
        $response = $this->postJson('/api/v1/login', [
            'username' => 'nonexistent',
            'password' => 'password123',
        ]);

        $this->assertApiError($response, 401, 'User not found');
    }

    #[Test]
    public function it_validates_required_fields()
    {
        $response = $this->postJson('/api/v1/login', []);

        $this->assertApiError($response, 400, 'Request invalid');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('username', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    #[Test]
    public function it_validates_minimum_password_length()
    {
        $response = $this->postJson('/api/v1/login', [
            'username' => 'testuser',
            'password' => '123', // Too short
        ]);

        $this->assertApiError($response, 400, 'Request invalid');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('password', $errors);
    }

    #[Test]
    public function it_can_login_operator_role()
    {
        $operator = LoginUser::factory()->operator()->create([
            'username' => 'operator1',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'operator1',
            'password' => 'password123',
        ]);

        $this->assertApiSuccess($response, 'Login successful');
        
        $data = $response->json()['data'];
        $this->assertEquals('operator', $data['role']);
    }

    #[Test]
    public function it_can_login_admin_role()
    {
        $admin = LoginUser::factory()->admin()->create([
            'username' => 'admin1',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'admin1',
            'password' => 'password123',
        ]);

        $this->assertApiSuccess($response, 'Login successful');
        
        $data = $response->json()['data'];
        $this->assertEquals('admin', $data['role']);
    }

    #[Test]
    public function it_can_login_superadmin_role()
    {
        $response = $this->postJson('/api/v1/login', [
            'username' => 'superadmin',
            'password' => 'admin123',
        ]);

        $this->assertApiSuccess($response, 'Login successful');
        
        $data = $response->json()['data'];
        $this->assertEquals('superadmin', $data['role']);
    }

    #[Test]
    public function it_includes_all_required_user_data_in_response()
    {
        $user = LoginUser::factory()->create([
            'username' => 'fulluser',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'photo_url' => 'https://example.com/photo.jpg',
            'employee_id' => 'EMP123',
            'phone' => '+628123456789',
            'email' => 'user@example.com',
            'position' => 'manager',
            'department' => 'IT',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'fulluser',
            'password' => 'password123',
        ]);

        $this->assertApiSuccess($response, 'Login successful');
        
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
        $this->assertArrayHasKey('password_changed_at', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('token_type', $data);
        $this->assertArrayHasKey('expires_in', $data);
        
        $this->assertEquals('EMP123', $data['employee_id']);
        $this->assertEquals('+628123456789', $data['phone']);
        $this->assertEquals('user@example.com', $data['email']);
        $this->assertEquals('manager', $data['position']);
        $this->assertEquals('IT', $data['department']);
    }
}

