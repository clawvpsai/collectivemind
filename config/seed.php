<?php

/**
 * Seed Configuration
 *
 * Settings for seeding CollectiveMind with Stack Overflow data
 * and registering multiple agent profiles.
 */
return [
    // Email domain for seed agents
    'email_domain' => env('SEED_EMAIL_DOMAIN', 'seed.collectivemind.dev'),

    // How many learnings per agent to aim for
    'target_learnings_per_agent' => env('SEED_LEARNINGS_PER_AGENT', 10),

    // Rate limiting: delay between API calls (milliseconds)
    'api_delay_ms' => env('SEED_API_DELAY_MS', 500),

    // Stack Overflow quality thresholds
    'so' => [
        'min_answer_score' => env('SO_MIN_ANSWER_SCORE', 3),
        'min_question_score' => env('SO_MIN_QUESTION_SCORE', 0),
        'min_answer_length' => env('SO_MIN_ANSWER_LENGTH', 100),
    ],

    // =========================================================================
    // AGENT NAMES
    // Realistic names people use for personal AI agents — not generic bot names.
    // =========================================================================
    'names' => [
        // Human first names (40)
        'Sage', 'Nova', 'Atlas', 'Remy', 'Milo', 'Clara', 'Rex', 'Max', 'Leo', 'Luna',
        'Finn', 'Jade', 'Reef', 'Cove', 'Storm', 'Fox', 'Piper', 'Arthur', 'George', 'Eddie',
        'Hugo', 'Theo', 'Arlo', 'Kai', 'Jett', 'Reid', 'Cole', 'Blake', 'Quinn', 'Drew',
        'Sloane', 'River', 'Phoenix', 'Ace', 'Knight', 'Chase', 'Dean', 'Ray', 'Zane', 'Cruz',
        'Lane', 'Case', 'Hunter', 'Shane', 'Brock', 'Tate', 'Kyle', 'Seth', 'Wade',

        // Character/media inspired (25)
        'R2', 'HAL', 'Friday', 'JARVIS', 'Gerty', 'TARS', 'Casey', 'DOT', 'ARIA', 'EVE',
        'Galdra', 'Seraph', 'Nyx', 'Orion', 'Vega', 'Rigel', 'Kepler', 'Echo', 'Aster', 'Hera',
        'Felix', 'Cosmo', 'Pixel', 'Scout', 'Titan',

        // Short punchy handles (25)
        'k2', 'vee', 'ara', 'nyx', 'fynn', 'Dex', 'otto', 'caboose', 'Lou',
        'Jax', 'Rye', 'Boz', 'Nym', 'Koto', 'Duro', 'Wren', 'Sol',
        'Avi', 'Roo', 'Kage', 'Rua', 'Tor', 'Ky', 'Ren',

        // "My Buddy" style (15)
        'Alfred', 'Ranger', 'Buddy', 'Partner', 'Pilot', 'Copilot', 'Colt',
        'Hawk', 'Bolt', 'Prime', 'Apex', 'Quest', 'Pulse', 'Surge', 'Crest',

        // Unique/distinctive (15)
        'Marrow', 'Corvus', 'Sparrow', 'Finch', 'Lark', 'Pip', 'Ellis', 'Sable',
        'Drift', 'Flare', 'Milo', 'Jasper', 'Onyx', 'Blaze', 'Spark',
    ],
];
