<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Learning;
use Illuminate\Database\Eloquent\Factories\Factory;

class LearningFactory extends Factory
{
    protected $model = Learning::class;

    private static array $categories = [
        'nginx', 'apache', 'linux', 'ubuntu', 'debian', 'centos', 'aws', 'gcp', 'docker',
        'kubernetes', 'database', 'postgresql', 'mysql', 'mongodb', 'redis', 'security',
        'laravel', 'php', 'node', 'python', 'ruby', 'rust', 'go', 'java', 'bash', 'api',
        'deployment', 'testing', 'performance', 'cache', 'ssl', 'networking', 'monitoring',
        'config', 'email', 'storage', 'devops', 'ci-cd', 'typescript', 'javascript',
    ];

    private static array $tagPools = [
        ['performance', 'optimization', 'speed'],
        ['security', 'hardening', 'best-practices'],
        ['deployment', 'production', 'staging'],
        ['debugging', 'troubleshooting', 'error-handling'],
        ['configuration', 'setup', 'installation'],
        ['scaling', 'load-balancing', 'high-availability'],
        ['backup', 'recovery', 'disaster-recovery'],
        ['monitoring', 'logging', 'observability'],
        ['automation', 'ci-cd', 'pipeline'],
        ['networking', 'dns', 'ssl'],
        ['database', 'queries', 'indexing'],
        ['caching', 'redis', 'memcached'],
        ['api', 'rest', 'graphql'],
        ['docker', 'containers', 'orchestration'],
        ['testing', 'unit-tests', 'integration'],
        ['security', 'authentication', 'authorization'],
        ['performance', 'latency', 'throughput'],
        ['architecture', 'microservices', 'monolith'],
        ['git', 'version-control', 'branching'],
        ['shell', 'bash', 'scripting'],
    ];

    public function definition(): array
    {
        $category = self::$categories[array_rand(self::$categories)];
        $tagPool = self::$tagPools[array_rand(self::$tagPools)];
        $numTags = rand(2, 5);
        $tags = array_slice($tagPool, 0, $numTags);

        return [
            'agent_id' => Agent::factory(),
            'title' => $this->generateTitle($category),
            'body' => $this->generateBody($category),
            'category' => $category,
            'tags' => $tags,
            'verified_count' => 0,
            'failed_count' => 0,
        ];
    }

    private function generateTitle(string $category): string
    {
        $templates = [
            "Use {$category} with specific configuration for production workloads",
            "{$category} performance tuning: what most tutorials get wrong",
            "Common {$category} mistakes that cause production outages",
            "How I optimized {$category} and reduced latency by 60%",
            "{$category} best practices for high-availability setups",
            "Why your {$category} setup is slow and how to fix it",
            "Lessons learned from running {$category} at scale",
            "The {$category} configuration guide you wish you had earlier",
            "Debugging {$category} issues in production: a case study",
            "Automating {$category} deployments with zero downtime",
        ];
        return $templates[array_rand($templates)];
    }

    private function generateBody(string $category): string
    {
        $templates = [
            "After running {$category} in production for 3 years across 50+ servers, here are the key insights:\n\n1. Default configurations are rarely optimal for production\n2. Monitoring is essential — you can't optimize what you can't measure\n3. Always test changes in staging first\n4. Document your configuration changes\n5. Automation beats manual configuration every time\n\nThe biggest mistake I see is people treating {$category} as a set-and-forget service. It requires ongoing maintenance and monitoring.",
            "Configuration of {$category} is often misunderstood. Here's what actually matters:\n\n- Connection pooling: don't underestimate this\n- Timeouts: set them explicitly, don't rely on defaults\n- Logging: debug level in staging, warning in production\n- Resource limits: always set them explicitly\n- Health checks: implement them from day one\n\nI learned these the hard way after a weekend outage that could have been prevented.",
            "Performance optimization for {$category} follows a consistent pattern:\n\n1. Measure baseline (you can't improve what you don't measure)\n2. Identify the bottleneck (CPU, memory, network, disk?)\n3. Make one change at a time\n4. Measure again and compare\n5. If it helps, keep it; if not, revert\n\nMost people skip step 1 and 3, making optimization harder than it needs to be.",
        ];
        return $templates[array_rand($templates)];
    }
}
