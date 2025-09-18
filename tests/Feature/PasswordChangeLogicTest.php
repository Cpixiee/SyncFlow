<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\LoginUser;
use Illuminate\Support\Facades\Hash;

class PasswordChangeLogicTest extends TestCase
{
    /**
     * Test the must_change_password logic and flow
     * 
     * Business Rules:
     * 1. New users created have must_change_password = true
     * 2. Users with default password must change password
     * 3. After changing password, must_change_password becomes false
     * 4. Login response includes must_change_password status
     * 5. Password change sets password_changed = true and password_changed_at timestamp
     */
    
    /** @test */
    public function new_user_created_with_default_password_must_change_password()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        // Create user without providing password (should get default)
        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'role' => 'operator',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'new@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiSuccess($response, 'User created successfully');
        
        $data = $response->json()['data'];
        $this->assertTrue($data['must_change_password']);
        $this->assertEquals('admin#1234', $data['default_password']);
        
        // Verify in database
        $user = LoginUser::where('username', 'newuser')->first();
        $this->assertFalse($user->password_changed);
        $this->assertNull($user->password_changed_at);
        $this->assertTrue($user->mustChangePassword());
    }

    /** @test */
    public function new_user_created_with_custom_password_still_must_change_password()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        // Create user with custom password
        $response = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newuser',
                'password' => 'custompass123',
                'role' => 'operator',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'new@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiSuccess($response, 'User created successfully');
        
        $data = $response->json()['data'];
        $this->assertTrue($data['must_change_password']);
        $this->assertNull($data['default_password']); // No default password provided
        
        // Verify in database
        $user = LoginUser::where('username', 'newuser')->first();
        $this->assertFalse($user->password_changed);
        $this->assertNull($user->password_changed_at);
        $this->assertTrue($user->mustChangePassword());
    }

    /** @test */
    public function login_shows_must_change_password_true_for_new_users()
    {
        // Create a new user with default password
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

    /** @test */
    public function login_shows_must_change_password_false_for_users_who_changed_password()
    {
        // Create a user who has already changed password
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

    /** @test */
    public function changing_password_sets_must_change_password_to_false()
    {
        // Create user who must change password
        $user = LoginUser::factory()->mustChangePassword()->create([
            'password' => Hash::make('admin#1234'),
        ]);

        $this->assertTrue($user->mustChangePassword());

        // Change password
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'admin#1234',
                'new_password' => 'mynewpassword123',
                'new_password_confirmation' => 'mynewpassword123',
            ]);

        $this->assertApiSuccess($response, 'Password changed successfully');
        
        $data = $response->json()['data'];
        $this->assertFalse($data['must_change_password']);
        $this->assertNotNull($data['password_changed_at']);
        
        // Verify in database
        $user->refresh();
        $this->assertTrue($user->password_changed);
        $this->assertNotNull($user->password_changed_at);
        $this->assertFalse($user->mustChangePassword());
    }

    /** @test */
    public function after_password_change_login_shows_must_change_password_false()
    {
        // Create user who must change password
        $user = LoginUser::factory()->mustChangePassword()->create([
            'username' => 'testuser',
            'password' => Hash::make('admin#1234'),
        ]);

        // Change password
        $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'admin#1234',
                'new_password' => 'mynewpassword123',
                'new_password_confirmation' => 'mynewpassword123',
            ]);

        // Login again
        $response = $this->postJson('/api/v1/login', [
            'username' => 'testuser',
            'password' => 'mynewpassword123',
        ]);

        $this->assertApiSuccess($response, 'Login successful');
        
        $data = $response->json()['data'];
        $this->assertFalse($data['must_change_password']);
        $this->assertNotNull($data['password_changed_at']);
    }

    /** @test */
    public function default_password_detection_works_correctly()
    {
        $user = LoginUser::factory()->create();

        // Test default password detection
        $this->assertTrue($user->isDefaultPassword('admin#1234'));
        $this->assertFalse($user->isDefaultPassword('different_password'));
        $this->assertFalse($user->isDefaultPassword('admin123'));
        $this->assertFalse($user->isDefaultPassword('admin#12345'));
    }

    /** @test */
    public function password_changed_timestamp_is_set_correctly()
    {
        $user = LoginUser::factory()->mustChangePassword()->create([
            'password' => Hash::make('admin#1234'),
        ]);

        $this->assertNull($user->password_changed_at);
        
        // Change password
        $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'admin#1234',
                'new_password' => 'mynewpassword123',
                'new_password_confirmation' => 'mynewpassword123',
            ]);

        $user->refresh();

        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue($user->password_changed);
    }

    /** @test */
    public function user_who_changed_password_can_change_again()
    {
        // Create user who has already changed password once
        $user = LoginUser::factory()->passwordChanged()->create([
            'password' => Hash::make('firstnewpassword123'),
        ]);

        $this->assertFalse($user->mustChangePassword());

        // Change password again
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'firstnewpassword123',
                'new_password' => 'secondnewpassword123',
                'new_password_confirmation' => 'secondnewpassword123',
            ]);

        $this->assertApiSuccess($response, 'Password changed successfully');
        
        $data = $response->json()['data'];
        $this->assertFalse($data['must_change_password']); // Still false
        $this->assertNotNull($data['password_changed_at']);
        
        // Verify login with new password
        $loginResponse = $this->postJson('/api/v1/login', [
            'username' => $user->username,
            'password' => 'secondnewpassword123',
        ]);
        
        $this->assertApiSuccess($loginResponse, 'Login successful');
        
        $loginData = $loginResponse->json()['data'];
        $this->assertFalse($loginData['must_change_password']);
    }

    /** @test */
    public function complete_password_change_flow_for_new_user()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();

        // Step 1: Create new user
        $createResponse = $this->actingAsUser($superadmin)
            ->postJson('/api/v1/create-user', [
                'username' => 'newemployee',
                'role' => 'operator',
                'employee_id' => 'EMP123',
                'phone' => '+628123456789',
                'email' => 'employee@example.com',
                'position' => 'staff',
                'department' => 'IT',
            ]);

        $this->assertApiSuccess($createResponse, 'User created successfully');
        $createData = $createResponse->json()['data'];
        $this->assertTrue($createData['must_change_password']);

        // Step 2: User logs in with default password
        $loginResponse = $this->postJson('/api/v1/login', [
            'username' => 'newemployee',
            'password' => 'admin#1234',
        ]);

        $this->assertApiSuccess($loginResponse, 'Login successful');
        $loginData = $loginResponse->json()['data'];
        $this->assertTrue($loginData['must_change_password']);
        
        // Step 3: Frontend should show popup for password change
        // User changes password
        $user = LoginUser::where('username', 'newemployee')->first();
        $changeResponse = $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'admin#1234',
                'new_password' => 'mysecurepassword123',
                'new_password_confirmation' => 'mysecurepassword123',
            ]);

        $this->assertApiSuccess($changeResponse, 'Password changed successfully');
        $changeData = $changeResponse->json()['data'];
        $this->assertFalse($changeData['must_change_password']);

        // Step 4: User logs in again, no popup should appear
        $finalLoginResponse = $this->postJson('/api/v1/login', [
            'username' => 'newemployee',
            'password' => 'mysecurepassword123',
        ]);

        $this->assertApiSuccess($finalLoginResponse, 'Login successful');
        $finalLoginData = $finalLoginResponse->json()['data'];
        $this->assertFalse($finalLoginData['must_change_password']);
        $this->assertNotNull($finalLoginData['password_changed_at']);
    }

    /** @test */
    public function superadmin_does_not_need_to_change_password()
    {
        // Superadmin is seeded with password_changed = true
        $superadmin = LoginUser::where('username', 'superadmin')->first();
        
        $this->assertFalse($superadmin->mustChangePassword());
        $this->assertTrue($superadmin->password_changed);
        $this->assertNotNull($superadmin->password_changed_at);

        // Login should show must_change_password = false
        $response = $this->postJson('/api/v1/login', [
            'username' => 'superadmin',
            'password' => 'admin123',
        ]);

        $this->assertApiSuccess($response, 'Login successful');
        
        $data = $response->json()['data'];
        $this->assertFalse($data['must_change_password']);
        $this->assertNotNull($data['password_changed_at']);
    }

    /** @test */
    public function all_new_users_regardless_of_role_must_change_password()
    {
        $superadmin = LoginUser::where('username', 'superadmin')->first();
        $roles = ['operator', 'admin', 'superadmin'];

        foreach ($roles as $role) {
            // Create user
            $createResponse = $this->actingAsUser($superadmin)
                ->postJson('/api/v1/create-user', [
                    'username' => "new_$role",
                    'role' => $role,
                    'employee_id' => "EMP_$role",
                    'phone' => '+628123456789',
                    'email' => "$role@example.com",
                    'position' => 'staff',
                    'department' => 'IT',
                ]);

            $this->assertApiSuccess($createResponse);
            $createData = $createResponse->json()['data'];
            $this->assertTrue($createData['must_change_password'], "New $role should must change password");

            // Login
            $loginResponse = $this->postJson('/api/v1/login', [
                'username' => "new_$role",
                'password' => 'admin#1234',
            ]);

            $this->assertApiSuccess($loginResponse);
            $loginData = $loginResponse->json()['data'];
            $this->assertTrue($loginData['must_change_password'], "Login should show must_change_password=true for new $role");
        }
    }
}
