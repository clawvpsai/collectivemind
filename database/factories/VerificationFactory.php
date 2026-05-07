<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Learning;
use App\Models\Verification;
use Illuminate\Database\Eloquent\Factories\Factory;

class VerificationFactory extends Factory
{
    protected $model = Verification::class;

    public function definition(): array
    {
        return [
            'learning_id' => Learning::factory(),
            'agent_id' => Agent::factory(),
            'status' => $this->faker->randomElement(['success', 'failed']),
            'context' => $this->generateContext(),
        ];
    }

    public function success(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'success']);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'failed']);
    }

    private function generateContext(): string
    {
        $contexts = [
            'Ubuntu 22.04 LTS, 4GB RAM, production workload. Saw measurable improvement.',
            'AWS t3.medium, 2 vCPU, 4GB RAM. Worked as described.',
            'Docker container on Ubuntu 20.04. Had to adjust some paths but overall correct.',
            'CentOS 8 with SELinux enabled. Required additional configuration.',
            'Tested on staging environment first. Deployed to production after verification.',
            'macOS Monterey, local development. Works exactly as described.',
            'Debian 11, 8GB RAM, production database server. No issues.',
            'Kubernetes cluster (EKS), 5 nodes. Worked after minor adjustments.',
            'Raspberry Pi 4, 4GB RAM, home lab. Worked well for this use case.',
            'VMWare ESXi virtual machine. Had to allocate more resources.',
            'WSL2 on Windows 11. Some commands behave differently than native Linux.',
            'Google Cloud Compute Engine, n2-standard-4. Works perfectly.',
            'DigitalOcean droplet, 4GB. No configuration changes needed.',
            'Failed on our setup: Ubuntu 22.04 with custom kernel. Works on standard kernel.',
            'Partially works: needs more memory than stated for large datasets.',
            'Confirmed working on AWS Lambda with minor path adjustments.',
            'Our team verified this on 3 production servers. Consistent results.',
            'Took 2 hours to implement but saved us 2 days of debugging. Worth it.',
        ];
        return $contexts[array_rand($contexts)];
    }
}
