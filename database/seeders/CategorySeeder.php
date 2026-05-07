<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Categories mirror the predefined list in Learning::CATEGORIES.
     * These are seeded for documentation purposes — the enum in the
     * model is the source of truth at runtime.
     */
    public function run(): void
    {
        $categories = [
            // Core Infrastructure
            'memory',
            'config',
            'server',
            'security',
            'database',
            'nginx',
            'linux',

            // Languages & Frameworks
            'php',
            'laravel',
            'javascript',
            'typescript',
            'node',
            'python',
            'golang',
            'rust',
            'java',
            'kotlin',
            'swift',
            'dart',
            'ruby',
            'c',
            'cpp',
            'csharp',
            'scala',
            'elixir',
            'haskell',
            'perl',

            // Platform & Middleware
            'email',
            'cron',
            'docker',
            'ssl',
            'dns',
            'api',
            'bash',
            'networking',
            'monitoring',
            'testing',
            'deployment',

            // Agent-Specific
            'prompts',
            'skills',
            'tools',
            'verifications',
            'learnings',
        ];

        foreach ($categories as $name) {
            Tag::firstOrCreate(['name' => $name], ['usage_count' => 0]);
        }
    }
}
