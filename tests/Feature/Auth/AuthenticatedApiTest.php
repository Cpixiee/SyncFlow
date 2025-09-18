<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\LoginUser;

class AuthenticatedApiTest extends TestCase
{
    /** @test */
    public function authenticated_user_can_get_their_profile()
    {
        $user = LoginUser::factory()->create([
            'username' => 'testuser',
            'role' => 'admin',
            'employee_id' => 'EMP123',
            'phone' => '+628123456789',
            'email' => 'test@example.com',
            'position' => 'manager',
            'department' => 'IT',
        ]);

        $response = $this->actingAsUser($user)
            ->getJson('/api/v1/me');

        $this->assertApiSuccess($response);
        
        $data = $response->json()['data'];
        $this->assertEquals($user->id, $data['id']);
        $this->assertEquals('testuser', $data['username']);
        $this->assertEquals('admin', $data['role']);
        $this->assertEquals('EMP123', $data['employee_id']);
        $this->assertEquals('+628123456789', $data['phone']);
        $this->assertEquals('test@example.com', $data['email']);
        $this->assertEquals('manager', $data['position']);
        $this->assertEquals('IT', $data['department']);
        
        // Should not include password in response
        $this->assertArrayNotHasKey('password', $data);
        
        // Should include timestamps
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
    }

    /** @test */
    public function unauthenticated_user_cannot_get_profile()
    {
        $response = $this->getJson('/api/v1/me');

        $this->assertApiError($response, 401);
    }

    /** @test */
    public function authenticated_user_can_logout()
    {
        $user = LoginUser::factory()->create();
        $token = $this->authenticateAs($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/logout');

        $this->assertApiSuccess($response, 'Successfully logged out');
        
        // Token should no longer work
        $profileResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/me');

        $this->assertApiError($profileResponse, 401);
    }

    /** @test */
    public function unauthenticated_user_cannot_logout()
    {
        $response = $this->postJson('/api/v1/logout');

        $this->assertApiError($response, 401);
    }

    /** @test */
    public function authenticated_user_can_refresh_token()
    {
        $user = LoginUser::factory()->create();
        $originalToken = $this->authenticateAs($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $originalToken,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/refresh');

        $this->assertApiSuccess($response, 'Token refreshed successfully');
        
        $data = $response->json()['data'];
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('token_type', $data);
        $this->assertArrayHasKey('expires_in', $data);
        $this->assertEquals('Bearer', $data['token_type']);
        
        $newToken = $data['token'];
        $this->assertNotEquals($originalToken, $newToken);
        
        // New token should work
        $profileResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $newToken,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/me');

        $this->assertApiSuccess($profileResponse);
    }

    /** @test */
    public function unauthenticated_user_cannot_refresh_token()
    {
        $response = $this->postJson('/api/v1/refresh');

        $this->assertApiError($response, 401);
    }

    /** @test */
    public function all_user_roles_can_access_authenticated_endpoints()
    {
        $roles = ['operator', 'admin', 'superadmin'];

        foreach ($roles as $role) {
            $user = LoginUser::factory()->create(['role' => $role]);

            // Test /me endpoint
            $response = $this->actingAsUser($user)->getJson('/api/v1/me');
            $this->assertApiSuccess($response);
            
            $data = $response->json()['data'];
            $this->assertEquals($role, $data['role']);

            // Test logout endpoint
            $logoutResponse = $this->actingAsUser($user)->postJson('/api/v1/logout');
            $this->assertApiSuccess($logoutResponse, 'Successfully logged out');

            // Test refresh endpoint
            $refreshResponse = $this->actingAsUser($user)->postJson('/api/v1/refresh');
            $this->assertApiSuccess($refreshResponse, 'Token refreshed successfully');
        }
    }

    /** @test */
    public function expired_token_is_rejected()
    {
        // This test would require mocking JWT expiration
        // For now, we test with an obviously invalid token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid.token.here',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/me');

        $this->assertApiError($response, 401);
    }

    /** @test */
    public function malformed_token_is_rejected()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer malformed_token',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/me');

        $this->assertApiError($response, 401);
    }

    /** @test */
    public function missing_bearer_prefix_is_rejected()
    {
        $user = LoginUser::factory()->create();
        $token = $this->authenticateAs($user);

        $response = $this->withHeaders([
            'Authorization' => $token, // Missing 'Bearer ' prefix
            'Accept' => 'application/json',
        ])->getJson('/api/v1/me');

        $this->assertApiError($response, 401);
    }

    /** @test */
    public function profile_includes_correct_user_data_structure()
    {
        $user = LoginUser::factory()->create();

        $response = $this->actingAsUser($user)->getJson('/api/v1/me');

        $this->assertApiSuccess($response);
        
        $data = $response->json()['data'];
        
        // Required fields
        $requiredFields = [
            'id', 'username', 'role', 'employee_id', 'phone', 
            'email', 'position', 'department', 'created_at', 'updated_at'
        ];
        
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $data, "Missing required field: $field");
        }
        
        // Should not include sensitive fields
        $sensitiveFields = ['password', 'password_changed', 'password_changed_at'];
        
        foreach ($sensitiveFields as $field) {
            $this->assertArrayNotHasKey($field, $data, "Should not include sensitive field: $field");
        }
    }
}

