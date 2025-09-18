<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\LoginUser;
use Illuminate\Support\Facades\Hash;

class ChangePasswordApiTest extends TestCase
{
    /** @test */
    public function authenticated_user_can_change_password()
    {
        $user = LoginUser::factory()->create([
            'password' => Hash::make('oldpassword123'),
        ]);

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'oldpassword123',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ]);

        $this->assertApiSuccess($response, 'Password changed successfully');
        
        $data = $response->json()['data'];
        $this->assertEquals($user->id, $data['id']);
        $this->assertEquals($user->username, $data['username']);
        $this->assertEquals($user->role, $data['role']);
        $this->assertFalse($data['must_change_password']);
        $this->assertNotNull($data['password_changed_at']);
        
        // Verify old password no longer works
        $loginResponse = $this->postJson('/api/v1/login', [
            'username' => $user->username,
            'password' => 'oldpassword123',
        ]);
        $this->assertApiError($loginResponse, 401);
        
        // Verify new password works
        $loginResponse = $this->postJson('/api/v1/login', [
            'username' => $user->username,
            'password' => 'newpassword123',
        ]);
        $this->assertApiSuccess($loginResponse, 'Login successful');
    }

    /** @test */
    public function it_marks_password_as_changed_for_first_time_users()
    {
        $user = LoginUser::factory()->mustChangePassword()->create([
            'password' => Hash::make('admin#1234'),
        ]);

        $this->assertTrue($user->mustChangePassword());
        $this->assertNull($user->password_changed_at);

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
        $this->assertFalse($user->mustChangePassword());
        $this->assertTrue($user->password_changed);
        $this->assertNotNull($user->password_changed_at);
    }

    /** @test */
    public function it_rejects_incorrect_current_password()
    {
        $user = LoginUser::factory()->create([
            'password' => Hash::make('correctpassword'),
        ]);

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'wrongpassword',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ]);

        $this->assertApiError($response, 400, 'Current password is incorrect');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('current_password', $errors);
    }

    /** @test */
    public function it_rejects_when_new_password_same_as_current()
    {
        $user = LoginUser::factory()->create([
            'password' => Hash::make('samepassword123'),
        ]);

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'samepassword123',
                'new_password' => 'samepassword123',
                'new_password_confirmation' => 'samepassword123',
            ]);

        $this->assertApiError($response, 400, 'New password must be different from current password');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('new_password', $errors);
    }

    /** @test */
    public function it_validates_password_confirmation()
    {
        $user = LoginUser::factory()->create([
            'password' => Hash::make('oldpassword123'),
        ]);

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'oldpassword123',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'differentpassword123',
            ]);

        $this->assertApiError($response, 400, 'Request invalid');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('new_password', $errors);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $user = LoginUser::factory()->create();

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', []);

        $this->assertApiError($response, 400, 'Request invalid');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('current_password', $errors);
        $this->assertArrayHasKey('new_password', $errors);
        $this->assertArrayHasKey('new_password_confirmation', $errors);
    }

    /** @test */
    public function it_validates_minimum_password_length()
    {
        $user = LoginUser::factory()->create([
            'password' => Hash::make('oldpassword123'),
        ]);

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'oldpassword123',
                'new_password' => '123', // Too short
                'new_password_confirmation' => '123',
            ]);

        $this->assertApiError($response, 400, 'Request invalid');
        
        $errors = $response->json()['data'];
        $this->assertArrayHasKey('new_password', $errors);
        $this->assertArrayHasKey('new_password_confirmation', $errors);
    }

    /** @test */
    public function unauthenticated_users_cannot_change_password()
    {
        $response = $this->postJson('/api/v1/change-password', [
            'current_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $this->assertApiError($response, 401);
    }

    /** @test */
    public function it_works_for_all_user_roles()
    {
        $roles = ['operator', 'admin', 'superadmin'];

        foreach ($roles as $role) {
            $user = LoginUser::factory()->create([
                'role' => $role,
                'password' => Hash::make('oldpassword123'),
            ]);

            $response = $this->actingAsUser($user)
                ->postJson('/api/v1/change-password', [
                    'current_password' => 'oldpassword123',
                    'new_password' => 'newpassword123',
                    'new_password_confirmation' => 'newpassword123',
                ]);

            $this->assertApiSuccess($response, 'Password changed successfully');
            
            $data = $response->json()['data'];
            $this->assertEquals($role, $data['role']);
            $this->assertFalse($data['must_change_password']);
        }
    }

    /** @test */
    public function it_handles_users_who_already_changed_password()
    {
        $user = LoginUser::factory()->passwordChanged()->create([
            'password' => Hash::make('currentpassword123'),
        ]);

        $this->assertFalse($user->mustChangePassword());

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'currentpassword123',
                'new_password' => 'newerpassword123',
                'new_password_confirmation' => 'newerpassword123',
            ]);

        $this->assertApiSuccess($response, 'Password changed successfully');
        
        $data = $response->json()['data'];
        $this->assertFalse($data['must_change_password']);
        $this->assertNotNull($data['password_changed_at']);
    }

    /** @test */
    public function it_updates_password_changed_timestamp()
    {
        $user = LoginUser::factory()->create([
            'password' => Hash::make('oldpassword123'),
            'password_changed' => false,
            'password_changed_at' => null,
        ]);

        $originalChangedAt = $user->password_changed_at;

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'oldpassword123',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ]);

        $this->assertApiSuccess($response, 'Password changed successfully');
        
        $user->refresh();
        $this->assertTrue($user->password_changed);
        $this->assertNotNull($user->password_changed_at);
        $this->assertNotEquals($originalChangedAt, $user->password_changed_at);
    }

    /** @test */
    public function it_can_change_from_default_password()
    {
        $user = LoginUser::factory()->defaultPassword()->create();

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'admin#1234',
                'new_password' => 'mynewsecurepassword123',
                'new_password_confirmation' => 'mynewsecurepassword123',
            ]);

        $this->assertApiSuccess($response, 'Password changed successfully');
        
        $data = $response->json()['data'];
        $this->assertFalse($data['must_change_password']);
        
        // Verify new password works
        $loginResponse = $this->postJson('/api/v1/login', [
            'username' => $user->username,
            'password' => 'mynewsecurepassword123',
        ]);
        $this->assertApiSuccess($loginResponse);
        
        $loginData = $loginResponse->json()['data'];
        $this->assertFalse($loginData['must_change_password']);
    }

    /** @test */
    public function it_preserves_user_data_except_password_related_fields()
    {
        $user = LoginUser::factory()->create([
            'username' => 'testuser',
            'role' => 'admin',
            'employee_id' => 'EMP123',
            'password' => Hash::make('oldpassword123'),
        ]);

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/change-password', [
                'current_password' => 'oldpassword123',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ]);

        $this->assertApiSuccess($response, 'Password changed successfully');
        
        $data = $response->json()['data'];
        $this->assertEquals('testuser', $data['username']);
        $this->assertEquals('admin', $data['role']);
        $this->assertEquals($user->id, $data['id']);
        
        // Verify user data unchanged in database
        $user->refresh();
        $this->assertEquals('testuser', $user->username);
        $this->assertEquals('admin', $user->role);
        $this->assertEquals('EMP123', $user->employee_id);
    }
}

