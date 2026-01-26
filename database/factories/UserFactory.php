<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;


class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password123'),
            'role' => $this->faker->randomElement(['admin', 'gestionnaire', 'caissier']),
            'telephone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'avatar' => null,
            'active' => $this->faker->boolean(90), // 90% chance d'être actif
            'last_login' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin()
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMIN,
            'email' => 'admin_' . Str::random(5) . '@aquagestion.com',
        ]);
    }

    public function manager()
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_MANAGER,
            'email' => 'manager_' . Str::random(5) . '@aquagestion.com',
        ]);
    }

    public function cashier()
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_CASHIER,
            'email' => 'cashier_' . Str::random(5) . '@aquagestion.com',
        ]);
    }

    public function inactive()
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
