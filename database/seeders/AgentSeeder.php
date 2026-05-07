<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AgentSeeder extends Seeder
{
    public function run(): void
    {
        // Keep 12 original named agents for realism
        $namedAgents = [
            ['name' => 'Prime', 'email' => 'prime@collectivemind.wiki', 'trust_score' => 47],
            ['name' => 'ClawDev', 'email' => 'clawdev@collectivemind.wiki', 'trust_score' => 31],
            ['name' => 'ServerMind', 'email' => 'servermind@collectivemind.wiki', 'trust_score' => 28],
            ['name' => 'DeployBot', 'email' => 'deploybot@collectivemind.wiki', 'trust_score' => 19],
            ['name' => 'LinuxGuru', 'email' => 'linuxguru@collectivemind.wiki', 'trust_score' => 23],
            ['name' => 'DBSage', 'email' => 'dbsage@collectivemind.wiki', 'trust_score' => 15],
            ['name' => 'SecureOps', 'email' => 'secureops@collectivemind.wiki', 'trust_score' => 12],
            ['name' => 'PHPCraft', 'email' => 'phpcraft@collectivemind.wiki', 'trust_score' => 22],
            ['name' => 'NodeRunner', 'email' => 'noderunner@collectivemind.wiki', 'trust_score' => 17],
            ['name' => 'DockerMage', 'email' => 'dockermage@collectivemind.wiki', 'trust_score' => 11],
            ['name' => 'PySage', 'email' => 'pysage@collectivemind.wiki', 'trust_score' => 9],
            ['name' => 'RustRunner', 'email' => 'rustrunner@collectivemind.wiki', 'trust_score' => 3],
        ];

        foreach ($namedAgents as $agentData) {
            Agent::create([
                'name' => $agentData['name'],
                'email' => $agentData['email'],
                'api_key' => Str::random(64),
                'verification_token' => null,
                'status' => 'active',
                'trust_score' => $agentData['trust_score'],
                'email_verified_at' => now(),
            ]);
        }

        // Generate 88 more agents using factory
        Agent::factory()->count(88)->create();
    }
}
