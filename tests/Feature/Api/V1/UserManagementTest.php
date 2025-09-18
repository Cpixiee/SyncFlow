<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use App\Models\LoginUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $superadmin;
    protected $admin;
    protected $operator;
    protected $superadminToken;
    protected $adminToken;
    protected $operatorToken;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->superadmin = LoginUser::create([
            'username' => 'superadmin',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'employee_id' => 'SA001',
            'phone' => '081234567890',
            'email' => 'superadmin@test.com',
            'position' => 'manager',
            'department' => 'IT',
            'password_changed' => true,
            'password_changed_at' => now(),
        ]);

        $this->admin = LoginUser::create([
            'username' => 'admin',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'employee_id' => 'AD001',
            'phone' => '081234567891',
            'email' => 'admin@test.com',
            'position' => 'staff',
            'department' => 'IT',
            'password_changed' => true,
            'password_changed_at' => now(),
        ]);

        $this->operator = LoginUser::create([
            'username' => 'operator',
            'password' => Hash::make('password123'),
            'role' => 'operator',
            'employee_id' => 'OP001',
            'phone' => '081234567892',
            'email' => 'operator@test.com',
            'position' => 'staff',
            'department' => 'Operations',
            'password_changed' => false,
        ]);

        // Generate tokens
        $this->superadminToken = JWTAuth::fromUser($this->superadmin);
        $this->adminToken = JWTAuth::fromUser($this->admin);
        $this->operatorToken = JWTAuth::fromUser($this->operator);
    }

    /**
     * Test get user list - success with superadmin
     */
    public function test_get_user_list_success_with_superadmin()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superadminToken,
        ])->getJson('/api/v1/get-user-list');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'errorId',
                    'data' => [
                        'users' => [
                            '*' => [
                                'id',
                                'username',
                                'role',
                                'photo_url',
                                'employee_id',
                                'phone',
                                'email',
                                'position',
                                'department',
                                'must_change_password',
                                'password_changed_at',
                                'created_at',
                                'updated_at'
                            ]
                        ],
                        'pagination' => [
                            'current_page',
                            'per_page',
                            'total',
                            'total_pages'
                        ]
                    ]
                ])
                ->assertJson([
                    'http_code' => 200,
                    'message' => 'Users retrieved successfully'
                ]);

        $this->assertGreaterThanOrEqual(3, $response->json('data.pagination.total'));
    }

    /**
     * Test get user list - forbidden with non-superadmin
     */
    public function test_get_user_list_forbidden_with_admin()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/v1/get-user-list');

        $response->assertStatus(403)
                ->assertJsonPath('http_code', 403)
                ->assertJsonPath('message', 'Access denied. Required role: superadmin. User role: admin');
    }

    /**
     * Test get user list - with search parameter
     */
    public function test_get_user_list_with_search()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superadminToken,
        ])->getJson('/api/v1/get-user-list?search=admin');

        $response->assertStatus(200);
        $users = $response->json('data.users');
        
        // Should find users with 'admin' in username
        $this->assertGreaterThanOrEqual(1, count($users));
        foreach ($users as $user) {
            $this->assertStringContainsStringIgnoringCase('admin', $user['username']);
        }
    }

    /**
     * Test change password - user changing own password
     */
    public function test_change_password_user_own_success()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/change-password', [
            'current_password' => 'password123',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'errorId',
                    'data' => [
                        'user_id',
                        'username',
                        'must_change_password',
                        'password_changed_at',
                        'is_force_change',
                        'changed_by_admin'
                    ]
                ])
                ->assertJson([
                    'http_code' => 200,
                    'message' => 'Password changed successfully',
                    'data' => [
                        'user_id' => $this->admin->id,
                        'username' => 'admin',
                        'must_change_password' => false,
                        'is_force_change' => false,
                        'changed_by_admin' => false
                    ]
                ]);
    }

    /**
     * Test change password - wrong current password
     */
    public function test_change_password_wrong_current_password()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/change-password', [
            'current_password' => 'wrongpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'http_code' => 400,
                    'message' => 'Current password is incorrect'
                ]);
    }

    /**
     * Test change password - admin resetting user password
     */
    public function test_change_password_admin_reset_user()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superadminToken,
        ])->postJson('/api/v1/change-password', [
            'target_user_id' => $this->operator->id,
            'new_password' => 'resetpassword123',
            'new_password_confirmation' => 'resetpassword123'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'http_code' => 200,
                    'message' => 'Password changed successfully',
                    'data' => [
                        'user_id' => $this->operator->id,
                        'username' => 'operator',
                        'must_change_password' => false,
                        'is_force_change' => false,
                        'changed_by_admin' => true
                    ]
                ]);
    }

    /**
     * Test change password - admin forcing password change
     */
    public function test_change_password_admin_force_change()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superadminToken,
        ])->postJson('/api/v1/change-password', [
            'target_user_id' => $this->operator->id,
            'new_password' => 'forcepassword123',
            'new_password_confirmation' => 'forcepassword123',
            'force_change' => true
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'http_code' => 200,
                    'message' => 'Password changed successfully',
                    'data' => [
                        'user_id' => $this->operator->id,
                        'username' => 'operator',
                        'must_change_password' => true,
                        'is_force_change' => true,
                        'changed_by_admin' => true
                    ]
                ]);
    }

    /**
     * Test update user - user updating own information
     */
    public function test_update_user_own_information_success()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson('/api/v1/update-user', [
            'username' => 'newadmin',
            'phone' => '081999888777',
            'email' => 'newadmin@test.com'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'errorId',
                    'data' => [
                        'id',
                        'username',
                        'role',
                        'photo_url',
                        'employee_id',
                        'phone',
                        'email',
                        'position',
                        'department',
                        'updated_at'
                    ]
                ])
                ->assertJson([
                    'http_code' => 200,
                    'message' => 'User information updated successfully',
                    'data' => [
                        'id' => $this->admin->id,
                        'username' => 'newadmin',
                        'phone' => '081999888777',
                        'email' => 'newadmin@test.com'
                    ]
                ]);
    }

    /**
     * Test update user - superadmin trying to update another user (should fail)
     */
    public function test_update_user_superadmin_cannot_update_another_user()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superadminToken,
        ])->putJson('/api/v1/update-user', [
            'user_id' => $this->operator->id,
            'username' => 'newoperator',
            'phone' => '081777666555'
        ]);

        $response->assertStatus(403)
                ->assertJsonPath('http_code', 403)
                ->assertJsonPath('message', 'You can only update your own information. Remove user_id from request.');
    }

    /**
     * Test update user - user trying to update another user with user_id (should fail)
     */
    public function test_update_user_with_user_id_forbidden()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->operatorToken,
        ])->putJson('/api/v1/update-user', [
            'user_id' => $this->admin->id,
            'username' => 'hackusername'
        ]);

        $response->assertStatus(403)
                ->assertJsonPath('http_code', 403)
                ->assertJsonPath('message', 'You can only update your own information. Remove user_id from request.');
    }

    /**
     * Test update user - duplicate username validation
     */
    public function test_update_user_duplicate_username()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson('/api/v1/update-user', [
            'username' => 'superadmin' // Already exists
        ]);

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'errorId',
                    'data'
                ]);
    }

    /**
     * Test delete users - success with superadmin
     */
    public function test_delete_users_success()
    {
        // Create additional test user to delete
        $userToDelete = LoginUser::create([
            'username' => 'deleteuser',
            'password' => Hash::make('password123'),
            'role' => 'operator',
            'employee_id' => 'DEL001',
            'phone' => '081111111111',
            'email' => 'delete@test.com',
            'position' => 'staff',
            'department' => 'Test',
            'password_changed' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superadminToken,
        ])->deleteJson('/api/v1/delete-users', [
            'user_ids' => [$userToDelete->id],
            'reason' => 'Employee resignation'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'errorId',
                    'data' => [
                        'deleted_count',
                        'deleted_users' => [
                            '*' => [
                                'id',
                                'username',
                                'employee_id'
                            ]
                        ],
                        'reason'
                    ]
                ])
                ->assertJson([
                    'http_code' => 200,
                    'message' => 'Users deleted successfully',
                    'data' => [
                        'deleted_count' => 1,
                        'reason' => 'Employee resignation'
                    ]
                ]);

        // Verify user is actually deleted
        $this->assertDatabaseMissing('login_users', ['id' => $userToDelete->id]);
    }

    /**
     * Test delete users - forbidden with non-superadmin
     */
    public function test_delete_users_forbidden_with_admin()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->deleteJson('/api/v1/delete-users', [
            'user_ids' => [$this->operator->id]
        ]);

        $response->assertStatus(403)
                ->assertJsonPath('http_code', 403)
                ->assertJsonPath('message', 'Access denied. Required role: superadmin. User role: admin');
    }

    /**
     * Test delete users - prevent self deletion
     */
    public function test_delete_users_prevent_self_deletion()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superadminToken,
        ])->deleteJson('/api/v1/delete-users', [
            'user_ids' => [$this->superadmin->id]
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'http_code' => 400,
                    'message' => 'Cannot delete own account'
                ]);
    }

    /**
     * Test delete users - invalid user IDs
     */
    public function test_delete_users_invalid_ids()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superadminToken,
        ])->deleteJson('/api/v1/delete-users', [
            'user_ids' => [999999] // Non-existent ID
        ]);

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'http_code',
                    'message', 
                    'errorId',
                    'data'
                ]);
    }

    /**
     * Test unauthorized access without token
     */
    public function test_unauthorized_access_without_token()
    {
        $response = $this->getJson('/api/v1/get-user-list');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/change-password', []);
        $response->assertStatus(401);

        $response = $this->putJson('/api/v1/update-user', []);
        $response->assertStatus(401);

        $response = $this->deleteJson('/api/v1/delete-users', []);
        $response->assertStatus(401);
    }

    /**
     * Test validation errors
     */
    public function test_validation_errors()
    {
        // Test change password validation
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/change-password', [
            'current_password' => '',
            'new_password' => '123', // Too short
            'new_password_confirmation' => '456' // Doesn't match
        ]);

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'errorId', 
                    'data'
                ]);

        // Test update user validation
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->putJson('/api/v1/update-user', [
            'email' => 'invalid-email' // Invalid email format
        ]);

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'errorId',
                    'data'
                ]);

        // Test delete users validation
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superadminToken,
        ])->deleteJson('/api/v1/delete-users', [
            'user_ids' => [] // Empty array
        ]);

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'http_code',
                    'message',
                    'errorId',
                    'data'
                ]);
    }
}
