<?php

namespace Database\Factories;

use App\Models\ClientCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientCompany>
 */
class ClientCompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_code' => 'CLI-PKDI-'.fake()->unique()->numerify('####'),
            'company_name' => 'PT '.fake()->company(),
            'pic_name' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => '021-'.fake()->numerify('########'),
            'address' => 'Kawasan Industri '.fake()->streetAddress(),
            'npwp' => null,
            'verification_status' => 'ACTIVE',
        ];
    }
}
