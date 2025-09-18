<?php

namespace Database\Factories;

use App\Models\LoginUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoginUser>
 */
class LoginUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LoginUser::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => $this->faker->unique()->userName,
            'password' => Hash::make('password123'),
            'role' => $this->faker->randomElement(['operator', 'admin', 'superadmin']),
            'photo_url' => $this->faker->imageUrl(200, 200, 'people'),
            'employee_id' => $this->faker->unique()->bothify('EMP###'),
            'phone' => $this->faker->phoneNumber,
            'email' => $this->faker->unique()->safeEmail,
            'position' => $this->faker->randomElement(['manager', 'staff', 'supervisor']),
            'department' => $this->faker->randomElement(['IT', 'HR', 'Finance', 'Operations', 'Marketing']),
            'password_changed' => $this->faker->boolean(50),
            'password_changed_at' => $this->faker->boolean(50) ? $this->faker->dateTimeBetween('-1 year', 'now') : null,
        ];
    }

    /**
     * State for operator role
     */
    public function operator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'operator',
        ]);
    }

    /**
     * State for admin role
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * State for superadmin role
     */
    public function superadmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'superadmin',
        ]);
    }

    /**
     * State for users who must change password
     */
    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'password_changed' => false,
            'password_changed_at' => null,
        ]);
    }

    /**
     * State for users who have changed password
     */
    public function passwordChanged(): static
    {
        return $this->state(fn (array $attributes) => [
            'password_changed' => true,
            'password_changed_at' => now(),
        ]);
    }

    /**
     * State for default password
     */
    public function defaultPassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => Hash::make('admin#1234'),
            'password_changed' => false,
            'password_changed_at' => null,
        ]);
    }
}

