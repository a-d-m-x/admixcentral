<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Firewall;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Firewall>
 */
class FirewallFactory extends Factory
{
    protected $model = Firewall::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->word() . '-firewall',
            'os_type' => 'pfsense',
            'url' => 'https://' . fake()->ipv4(),
            'auth_method' => 'token',
            'api_token' => Str::random(32),
            'netgate_id' => 'netgate-' . Str::random(12),
        ];
    }
}
