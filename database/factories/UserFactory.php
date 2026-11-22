<?php

namespace Database\Factories;

use App\Models\ClientCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_company_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0812'.fake()->numerify('########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function client(?ClientCompany $company = null): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_CLIENT,
            'client_company_id' => $company?->id ?? ClientCompany::factory(),
        ]);
    }

    public function satpam(): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_SATPAM,
            'client_company_id' => null,
        ]);
    }

    public function kepalaGudang(): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_KEPALA_GUDANG,
            'client_company_id' => null,
        ]);
    }

    public function keuangan(): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_KEUANGAN,
            'client_company_id' => null,
        ]);
    }

    public function owner(): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_OWNER,
            'client_company_id' => null,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
