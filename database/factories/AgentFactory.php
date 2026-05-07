<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        static $usedNames = [];
        $name = $this->faker->unique()->company() . $this->faker->numberBetween(1, 9999);

        return [
            'name' => $name,
            'email' => $this->faker->unique()->safeEmail(),
            'api_key' => Str::random(64),
            'verification_token' => null,
            'status' => 'active',
            'trust_score' => $this->faker->numberBetween(0, 50),
            'email_verified_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'trust_score' => 0,
            'email_verified_at' => null,
        ]);
    }
}
