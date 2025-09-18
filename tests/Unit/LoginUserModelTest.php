<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\LoginUser;
use Illuminate\Support\Facades\Hash;

class LoginUserModelTest extends TestCase
{
    /** @test */
    public function it_can_create_a_login_user()
    {
        $user = LoginUser::factory()->create([
            'username' => 'testuser',
            'role' => 'admin',
        ]);

        $this->assertDatabaseHas('login_users', [
            'username' => 'testuser',
            'role' => 'admin',
        ]);
    }

    /** @test */
    public function it_hashes_password_automatically()
    {
        $user = LoginUser::factory()->create([
            'password' => 'plaintext_password',
        ]);

        $this->assertTrue(Hash::check('plaintext_password', $user->fresh()->password));
    }

    /** @test */
    public function it_can_check_if_user_has_specific_role()
    {
        $operator = LoginUser::factory()->operator()->create();
        $admin = LoginUser::factory()->admin()->create();
        $superadmin = LoginUser::factory()->superadmin()->create();

        $this->assertTrue($operator->hasRole('operator'));
        $this->assertFalse($operator->hasRole('admin'));

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertFalse($admin->hasRole('operator'));

        $this->assertTrue($superadmin->hasRole('superadmin'));
        $this->assertFalse($superadmin->hasRole('admin'));
    }

    /** @test */
    public function it_can_check_if_user_is_admin()
    {
        $operator = LoginUser::factory()->operator()->create();
        $admin = LoginUser::factory()->admin()->create();
        $superadmin = LoginUser::factory()->superadmin()->create();

        $this->assertFalse($operator->isAdmin());
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($superadmin->isAdmin());
    }

    /** @test */
    public function it_can_check_if_user_is_superadmin()
    {
        $operator = LoginUser::factory()->operator()->create();
        $admin = LoginUser::factory()->admin()->create();
        $superadmin = LoginUser::factory()->superadmin()->create();

        $this->assertFalse($operator->isSuperAdmin());
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertTrue($superadmin->isSuperAdmin());
    }

    /** @test */
    public function it_correctly_identifies_users_who_must_change_password()
    {
        $userMustChange = LoginUser::factory()->mustChangePassword()->create();
        $userChanged = LoginUser::factory()->passwordChanged()->create();

        $this->assertTrue($userMustChange->mustChangePassword());
        $this->assertFalse($userChanged->mustChangePassword());
    }

    /** @test */
    public function it_can_mark_password_as_changed()
    {
        $user = LoginUser::factory()->mustChangePassword()->create();
        
        $this->assertTrue($user->mustChangePassword());
        $this->assertNull($user->password_changed_at);

        $user->markPasswordChanged();
        $user->refresh();

        $this->assertFalse($user->mustChangePassword());
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue($user->password_changed);
    }

    /** @test */
    public function it_can_identify_default_password()
    {
        $user = LoginUser::factory()->defaultPassword()->create();

        $this->assertTrue($user->isDefaultPassword('admin#1234'));
        $this->assertFalse($user->isDefaultPassword('different_password'));
    }

    /** @test */
    public function it_can_scope_users_by_role()
    {
        LoginUser::factory()->operator()->count(3)->create();
        LoginUser::factory()->admin()->count(2)->create();
        LoginUser::factory()->superadmin()->count(1)->create();

        $operators = LoginUser::role('operator')->get();
        $admins = LoginUser::role('admin')->get();
        $superadmins = LoginUser::role('superadmin')->get();

        $this->assertCount(3, $operators);
        $this->assertCount(2, $admins);
        $this->assertCount(2, $superadmins); // 1 from factory + 1 from setUp()
    }

    /** @test */
    public function it_has_correct_jwt_identifier()
    {
        $user = LoginUser::factory()->create();
        
        $this->assertEquals($user->id, $user->getJWTIdentifier());
    }

    /** @test */
    public function it_has_correct_jwt_custom_claims()
    {
        $user = LoginUser::factory()->create([
            'role' => 'admin',
            'employee_id' => 'EMP123',
        ]);

        $claims = $user->getJWTCustomClaims();

        $this->assertEquals('admin', $claims['role']);
        $this->assertEquals('EMP123', $claims['employee_id']);
    }

    /** @test */
    public function it_hides_password_in_serialization()
    {
        $user = LoginUser::factory()->create();

        $userArray = $user->toArray();
        
        $this->assertArrayNotHasKey('password', $userArray);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $user = LoginUser::factory()->passwordChanged()->create();

        $this->assertIsBool($user->password_changed);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->password_changed_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->updated_at);
    }

    /** @test */
    public function it_validates_role_enum_values()
    {
        $validRoles = ['operator', 'admin', 'superadmin'];
        
        foreach ($validRoles as $role) {
            $user = LoginUser::factory()->create(['role' => $role]);
            $this->assertEquals($role, $user->role);
        }
    }

    /** @test */
    public function it_validates_position_enum_values()
    {
        $validPositions = ['manager', 'staff', 'supervisor'];
        
        foreach ($validPositions as $position) {
            $user = LoginUser::factory()->create(['position' => $position]);
            $this->assertEquals($position, $user->position);
        }
    }
}

