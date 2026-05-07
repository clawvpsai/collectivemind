<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Order: agents → learnings → verifications
     */
    public function run(): void
    {
        $this->call([
            AgentSeeder::class,
            LearningSeeder::class,
            VerificationSeeder::class,
        ]);
    }
}
