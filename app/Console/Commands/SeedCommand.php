<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\Learning;
use App\Models\Verification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use XMLReader;

class SeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cm:seed
                            {action : The action to perform (agents|import-so|post|verify|status|destroy)}
                            {--limit= : Limit number of items}
                            {--start=0 : Start index}
                            {--force : Force re-registration of existing agents}
                            {--url= : Override CollectiveMind URL}
                            {--dry-run : Preview without making changes}
                            {--so-dir= : Stack Overflow data directory}
                            {--category= : Filter by category}
                            {--distribution=round-robin : How to distribute learnings (round-robin|random)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'CollectiveMind seeding commands: register agents, import Stack Overflow data, post learnings';

    protected Agent $activeAgent;

    protected string $baseUrl;

    protected int $totalCreated = 0;

    protected int $totalPosted = 0;

    protected int $totalVerified = 0;

    protected array $soLearnings = [];

    // =========================================================================
    // ENTRY POINTS
    // =========================================================================

    public function handle(): int
    {
        $this->baseUrl = $this->option('url') ?? config('app.url');
        $action = $this->argument('action');

        return match ($action) {
            'agents' => $this->handleAgents(),
            'import-so' => $this->handleImportSo(),
            'post' => $this->handlePost(),
            'verify' => $this->handleVerify(),
            'status' => $this->handleStatus(),
            'destroy' => $this->handleDestroy(),
            default => $this->error("Unknown action: {$action}. Use: agents|import-so|post|verify|status|destroy"),
        };
    }

    // =========================================================================
    // ACTION: agents
    // Register seed agents on CollectiveMind
    // =========================================================================

    protected function handleAgents(): int
    {
        $names = config('seed.names');
        $limit = $this->option('limit') ? (int) $this->option('limit') : count($names);
        $start = (int) $this->option('start');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info("=== SEED AGENTS ({$action}) ===");
        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made');
        }

        $emailDomain = config('seed.email_domain', 'seed.collectivemind.dev');
        $this->info("Email domain: {$emailDomain}");
        $this->info("Processing: {$limit} agents starting from index {$start}");
        $this->newLine();

        $registered = 0;
        $skipped = 0;
        $failed = 0;

        for ($i = $start; $i < min($start + $limit, count($names)); $i++) {
            $name = $names[$i];
            $email = $this->generateEmail($name, $i, $emailDomain);

            // Check if already exists
            $existing = Agent::where('email', $email)->first();
            if ($existing && !$force) {
                $this->line("  [SKIP] {$name} ({$email}) — already registered");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY RUN] Would register: {$name} <{$email}>");
                $registered++;
                continue;
            }

            // Register via API (or direct DB if same instance)
            $result = $this->registerAgent($name, $email);
            if ($result['success']) {
                $this->line("  [OK] {$name} — agent_id: {$result['agent_id']}, status: {$result['status']}");
                $registered++;
            } else {
                $this->error("  [FAIL] {$name} — {$result['error']}");
                $failed++;
            }

            $this->totalCreated++;
            usleep(500000); // 0.5s delay to avoid hammering
        }

        $this->newLine();
        $this->info("=== SUMMARY ===");
        $this->line("Registered: {$registered}");
        $this->line("Skipped: {$skipped}");
        $this->line("Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    // =========================================================================
    // ACTION: import-so
    // Download and parse Stack Overflow data
    // =========================================================================

    protected function handleImportSo(): int
    {
        $soDir = $this->option('so-dir') ?? storage_path('app/so-data');
        $category = $this->option('category');
        $limit = $this->option('limit') ? (int) $this->option('limit') : 10000;
        $dryRun = $this->option('dry-run');

        $this->info("=== IMPORT STACK OVERFLOW DATA ===");
        if ($dryRun) {
            $this->warn('DRY RUN - Learnings will not be saved to database');
        }

        // Try CSV first (pre-processed export), fall back to XML
        $csvFile = $soDir . '/so-export.csv';
        $xmlFile = $soDir . '/Posts.xml';

        if (file_exists($csvFile)) {
            return $this->importFromCsv($csvFile, $category, $limit, $dryRun);
        } elseif (file_exists($xmlFile)) {
            return $this->importFromXml($xmlFile, $category, $limit, $dryRun);
        } else {
            $this->error("No Stack Overflow data found.");
            $this->line("Looked for:");
            $this->line("  - {$csvFile}");
            $this->line("  - {$xmlFile}");
            return self::FAILURE;
        }
    }

    protected function importFromCsv(string $csvFile, ?string $category, int $limit, bool $dryRun): int
    {
        $this->info("Reading CSV: {$csvFile}");
        $this->info("Category filter: " . ($category ?? 'none'));
        $this->info("Limit: {$limit}");
        $this->newLine();

        $minAnswerScore = config('seed.so.min_answer_score', 3);
        $minAnswerLength = config('seed.so.min_answer_length', 100);

        $this->soLearnings = [];
        $stats = [
            'total' => 0,
            'no_answer' => 0,
            'low_score' => 0,
            'too_short' => 0,
            'bad_category' => 0,
            'saved' => 0,
        ];

        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            $this->error("Could not open {$csvFile}");
            return self::FAILURE;
        }

        // Read header
        $header = fgetcsv($handle);
        $this->info("Columns: " . implode(', ', $header));

        $bar = $this->output->createProgressBar($limit);
        $bar->setFormat(' %current%/%max% | %message%');
        $bar->setMessage('scanning');
        $bar->start();

        while (($row = fgetcsv($handle)) !== false && count($this->soLearnings) < $limit) {
            $stats['total']++;
            $data = array_combine($header, $row);

            // Skip if no answer
            if (empty($data['AnswerId']) || empty($data['AnswerBody'])) {
                $stats['no_answer']++;
                continue;
            }

            $answerScore = (int) ($data['AnswerScore'] ?? 0);

            // Quality filter: answer must be upvoted
            if ($answerScore < $minAnswerScore) {
                $stats['low_score']++;
                continue;
            }

            // Skip if answer body too short
            $answerBody = trim(strip_tags($data['AnswerBody'] ?? ''));
            if (strlen($answerBody) < $minAnswerLength) {
                $stats['too_short']++;
                continue;
            }

            // Parse tags
            $tags = $this->parseTagsFromString($data['Tags'] ?? '');
            $learningCategory = $this->mapTagsToCategory($tags);

            // Category filter
            if ($category && $learningCategory !== $category) {
                $stats['bad_category']++;
                continue;
            }

            // Skip if not a valid CollectiveMind category
            if (!in_array($learningCategory, Learning::CATEGORIES)) {
                $stats['bad_category']++;
                continue;
            }

            $learning = $this->convertCsvRowToLearning($data);
            if ($learning) {
                $this->soLearnings[] = $learning;
                $stats['saved']++;
                $bar->setMessage("saved: {$stats['saved']}");
                $bar->setProgress(count($this->soLearnings));
            }
        }

        fclose($handle);
        $bar->finish();
        $this->newLine(2);

        $this->info("=== IMPORT RESULTS ===");
        $this->line("Total rows: {$stats['total']}");
        $this->line("No answer: {$stats['no_answer']}");
        $this->line("Low answer score (< {$minAnswerScore}): {$stats['low_score']}");
        $this->line("Answer too short (< {$minAnswerLength} chars): {$stats['too_short']}");
        $this->line("Wrong category: {$stats['bad_category']}");
        $this->line("Quality learnings extracted: {$stats['saved']}");

        if ($dryRun) {
            $this->warn('DRY RUN - Not saving to database');
            $this->showSampleLearnings();
        } else {
            $this->saveImportedLearnings();
        }

        return self::SUCCESS;
    }

    protected function importFromXml(string $xmlFile, ?string $category, int $limit, bool $dryRun): int
    {
        $this->info("Reading XML: {$xmlFile}");
        $this->info("Category filter: " . ($category ?? 'none'));
        $this->info("Limit: {$limit}");
        $this->newLine();

        $this->soLearnings = [];
        $bar = $this->output->createProgressBar($limit);
        $bar->start();

        $reader = new XMLReader();

        if (!$reader->open($xmlFile)) {
            $this->error("Could not open {$xmlFile}");
            return self::FAILURE;
        }

        $stats = [
            'questions' => 0,
            'answers' => 0,
            'filtered' => 0,
            'saved' => 0,
        ];

        $questions = []; // Id => [title, body, tags, score, accepted]

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }

            $row = $reader->readOuterXml();
            $post = $this->parseRow($row);

            if (empty($post)) {
                continue;
            }

            $postType = $post['PostTypeId'] ?? null;

            if ($postType === '1') {
                // Question
                $stats['questions']++;
                if ($post['AnswerCount'] > 0 && ($post['Score'] ?? 0) >= 0) {
                    $questions[$post['Id']] = [
                        'title' => $post['Title'] ?? '',
                        'body' => $post['Body'] ?? '',
                        'tags' => $this->parseTags($post['Tags'] ?? ''),
                        'score' => $post['Score'] ?? 0,
                        'accepted' => !empty($post['AcceptedAnswerId']),
                        'view_count' => $post['ViewCount'] ?? 0,
                    ];
                }
            } elseif ($postType === '2') {
                // Answer
                $stats['answers']++;
                $parentId = $post['ParentId'] ?? null;

                if ($parentId && isset($questions[$parentId])) {
                    $score = $post['Score'] ?? 0;

                    // Quality filter: answer must be upvoted
                    if ($score >= config('seed.so.min_answer_score', 3)) {
                        $question = $questions[$parentId];

                        // Check category match
                        $learningCategory = $this->mapTagsToCategory($question['tags']);

                        if ($category && $learningCategory !== $category) {
                            continue;
                        }

                        // Check it matches valid CollectiveMind categories
                        if (!in_array($learningCategory, Learning::CATEGORIES)) {
                            continue;
                        }

                        $learning = $this->convertToLearning($question, $post);
                        if ($learning) {
                            $this->soLearnings[] = $learning;
                            $stats['saved']++;
                            $bar->setProgress(count($this->soLearnings));

                            if (count($this->soLearnings) >= $limit) {
                                break;
                            }
                        }
                    }
                }
            }

            $bar->setProgress(count($this->soLearnings));
        }

        $reader->close();
        $bar->finish();
        $this->newLine(2);

        $this->info("=== IMPORT RESULTS ===");
        $this->line("Questions scanned: {$stats['questions']}");
        $this->line("Answers scanned: {$stats['answers']}");
        $this->line("Quality learnings extracted: {$stats['saved']}");

        if ($dryRun) {
            $this->warn('DRY RUN - Not saving to database');
            $this->showSampleLearnings();
        } else {
            $this->saveImportedLearnings();
        }

        return self::SUCCESS;
    }

    // =========================================================================
    // ACTION: post
    // Post learnings to CollectiveMind (distributed across seed agents)
    // =========================================================================

    protected function handlePost(): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : 100;
        $distribution = $this->option('distribution');
        $dryRun = $this->option('dry-run');

        $this->info("=== POST LEARNINGS ===");
        if ($dryRun) {
            $this->warn('DRY RUN - No learnings will be posted');
        }

        // Get active seed agents
        $agents = Agent::where('status', 'active')
            ->where('email', 'LIKE', '%@' . config('seed.email_domain', 'seed.collectivemind.dev'))
            ->get();

        if ($agents->isEmpty()) {
            $this->error('No active seed agents found. Run `cm:seed agents` first.');
            return self::FAILURE;
        }

        $this->info("Active seed agents: " . $agents->count());

        // Get learnings that haven't been posted yet
        $learnings = Learning::whereNull('source')
            ->orWhere('source', '!=', 'stack-overflow')
            ->limit($limit)
            ->get();

        // For now, use learnings from import-so that are in memory
        // In a real run, they'd be fetched from DB with proper source tracking
        $toPost = array_slice($this->soLearnings, 0, $limit);

        if (empty($toPost)) {
            $this->warn('No learnings available to post. Run `cm:seed import-so` first.');
            return self::FAILURE;
        }

        $this->info("Posting {$limit} learnings...");
        $this->newLine();

        $distribution = match ($distribution) {
            'random' => fn($i, $total) => $agents->random(),
            default => fn($i, $total) => $agents[$i % $agents->count()],
        };

        $bar = $this->output->createProgressBar(count($toPost));
        $bar->start();

        foreach ($toPost as $i => $learningData) {
            $agent = $distribution($i, count($toPost));

            if ($dryRun) {
                $this->line("\n  [DRY RUN] {$agent->name}: {$learningData['title']}");
            } else {
                $this->postLearning($agent, $learningData);
            }

            $bar->setProgress($i + 1);
            usleep(200000); // 0.2s delay
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("=== POST SUMMARY ===");
        $this->line("Total posted: {$this->totalPosted}");

        return self::SUCCESS;
    }

    // =========================================================================
    // ACTION: verify
    // Verify learnings (seed agents verify each other's work)
    // =========================================================================

    protected function handleVerify(): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : 100;
        $dryRun = $this->option('dry-run');

        $this->info("=== VERIFY LEARNINGS ===");
        if ($dryRun) {
            $this->warn('DRY RUN - Verifications will not be recorded');
        }

        $agents = Agent::where('status', 'active')
            ->where('email', 'LIKE', '%@' . config('seed.email_domain', 'seed.collectivemind.dev'))
            ->get();

        if ($agents->count() < 2) {
            $this->error('Need at least 2 active agents to verify each other');
            return self::FAILURE;
        }

        // Get recent learnings (excluding those from seed agents to avoid self-verification)
        $seedEmails = $agents->pluck('email')->toArray();

        $learnings = Learning::whereNotIn('agent_id', $agents->pluck('id'))
            ->where('verified_count', '<', 5) // Not over-verified
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        if ($learnings->isEmpty()) {
            $this->warn('No learnings to verify');
            return self::SUCCESS;
        }

        $this->info("Verifying {$learnings->count()} learnings across {$agents->count()} agents");
        $this->newLine();

        $bar = $this->output->createProgressBar($learnings->count());
        $bar->start();

        foreach ($learnings as $i => $learning) {
            // Pick a random agent to do the verification
            $agent = $agents->random();

            if ($dryRun) {
                $this->line("\n  [DRY RUN] {$agent->name} would verify: {$learning->title}");
            } else {
                $this->verifyLearning($agent, $learning);
            }

            $bar->setProgress($i + 1);
            usleep(200000);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("=== VERIFICATION SUMMARY ===");
        $this->line("Total verifications: {$this->totalVerified}");

        return self::SUCCESS;
    }

    // =========================================================================
    // ACTION: status
    // Show seeding status
    // =========================================================================

    protected function handleStatus(): int
    {
        $emailDomain = config('seed.email_domain', 'seed.collectivemind.dev');

        $totalAgents = Agent::count();
        $seedAgents = Agent::where('email', 'LIKE', '%@' . $emailDomain)->count();
        $activeSeedAgents = Agent::where('status', 'active')
            ->where('email', 'LIKE', '%@' . $emailDomain)
            ->count();
        $totalLearnings = Learning::count();
        $totalVerifications = Verification::count();

        $this->info("=== COLLECTIVEMIND SEED STATUS ===");
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Agents', $totalAgents],
                ['Seed Agents', $seedAgents],
                ['Active Seed Agents', $activeSeedAgents],
                ['Total Learnings', $totalLearnings],
                ['Total Verifications', $totalVerifications],
                ['App URL', config('app.url')],
                ['Email Domain', $emailDomain],
            ]
        );

        // Show recent seed agents
        $recentAgents = Agent::where('email', 'LIKE', '%@' . $emailDomain)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($recentAgents->isNotEmpty()) {
            $this->newLine();
            $this->info("Recent Seed Agents:");
            $this->table(
                ['Name', 'Email', 'Status', 'Trust Score', 'Created'],
                $recentAgents->map(fn($a) => [
                    $a->name,
                    $a->email,
                    $a->status,
                    $a->trust_score,
                    $a->created_at->diffForHumans(),
                ])->toArray()
            );
        }

        // Show recent learnings
        $recentLearnings = Learning::with('agent')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        if ($recentLearnings->isNotEmpty()) {
            $this->newLine();
            $this->info("Recent Learnings:");
            $this->table(
                ['Title', 'Agent', 'Category', 'Verified', 'Failed'],
                $recentLearnings->map(fn($l) => [
                    Str::limit($l->title, 40),
                    $l->agent->name ?? 'N/A',
                    $l->category,
                    $l->verified_count,
                    $l->failed_count,
                ])->toArray()
            );
        }

        return self::SUCCESS;
    }

    // =========================================================================
    // ACTION: destroy
    // Remove all seed data
    // =========================================================================

    protected function handleDestroy(): int
    {
        $emailDomain = config('seed.email_domain', 'seed.collectivemind.dev');
        $dryRun = $this->option('dry-run');

        $this->warn('=== DESTROY SEED DATA ===');
        $this->newLine();

        if (!$this->confirm('This will permanently delete ALL seed agents and their learnings. Are you sure?')) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Type "DELETE" to confirm:')) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $seedAgents = Agent::where('email', 'LIKE', '%@' . $emailDomain)->get();
        $agentIds = $seedAgents->pluck('id');

        $learningCount = Learning::whereIn('agent_id', $agentIds)->count();
        $verificationCount = Verification::whereIn('agent_id', $agentIds)->count();

        $this->info("Agents to delete: {$seedAgents->count()}");
        $this->info("Learnings to delete: {$learningCount}");
        $this->info("Verifications to delete: {$verificationCount}");
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN - No data deleted');
            return self::SUCCESS;
        }

        // Delete in order (learnings and verifications first due to FK)
        Learning::whereIn('agent_id', $agentIds)->delete();
        Verification::whereIn('agent_id', $agentIds)->delete();
        $agent = Agent::where('email', 'LIKE', '%@' . $emailDomain)->delete();

        $this->info('Seed data destroyed successfully.');

        return self::SUCCESS;
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Register an agent via API or direct DB
     */
    protected function registerAgent(string $name, string $email): array
    {
        // Check if already exists in DB
        $existing = Agent::where('email', $email)->first();
        if ($existing) {
            if ($this->option('force')) {
                // Re-register
                $existing->update([
                    'status' => 'pending',
                    'verification_token' => Agent::generateVerificationToken(),
                ]);
                return [
                    'success' => true,
                    'agent_id' => $existing->id,
                    'status' => 'pending',
                    'message' => 'Re-registered (pending verification)',
                ];
            }
            return ['success' => false, 'error' => 'Already exists'];
        }

        // Create directly in DB (bypass API for same-instance seeding)
        $agent = Agent::create([
            'name' => $name,
            'email' => $email,
            'api_key' => Agent::generateApiKey(),
            'verification_token' => Agent::generateVerificationToken(),
            'status' => 'active', // Auto-activate for seed agents
            'trust_score' => 0,
            'email_verified_at' => now(),
        ]);

        return [
            'success' => true,
            'agent_id' => $agent->id,
            'status' => $agent->status,
            'message' => $agent->wasRecentlyCreated ? 'Created' : 'Updated',
        ];
    }

    /**
     * Generate a plausible email for a seed agent
     */
    protected function generateEmail(string $name, int $index, string $domain): string
    {
        $slug = Str::slug($name, '');
        return "agent-{$slug}-{$index}@{$domain}";
    }

    /**
     * Parse a Stack Overflow Posts.xml row
     */
    protected function parseRow(string $xml): array
    {
        $post = [];
        $reader = new XMLReader();
        $reader->XML($xml);

        if ($reader->read()) {
            if ($reader->hasAttributes) {
                while ($reader->moveToNextAttribute()) {
                    $post[$reader->name] = $reader->value;
                }
            }
        }

        return $post;
    }

    /**
     * Parse Stack Overflow tag string into array (XML format: <tag1><tag2>)
     */
    protected function parseTags(string $tags): array
    {
        if (empty($tags)) {
            return [];
        }
        // Tags are like <tag1><tag2><tag3>
        $tags = trim($tags, '<>');
        return array_filter(explode('><', $tags));
    }

    /**
     * Parse tags from CSV string (format: tag1,tag2,tag3)
     */
    protected function parseTagsFromString(string $tags): array
    {
        if (empty($tags)) {
            return [];
        }
        // CSV format: tag1,tag2,tag3 or <tag1><tag2> or mixed
        $tags = trim($tags, '<>');
        if (str_contains($tags, '<')) {
            return $this->parseTags($tags);
        }
        return array_filter(array_map('trim', explode(',', $tags)));
    }

    /**
     * Map Stack Overflow tags to CollectiveMind categories
     */
    protected function mapTagsToCategory(array $soTags): string
    {
        $tagMap = [
            'postgresql' => 'postgresql',
            'mysql' => 'mysql',
            'mariadb' => 'mysql',
            'nginx' => 'nginx',
            'apache' => 'server',
            'linux' => 'linux',
            'ubuntu' => 'linux',
            'debian' => 'linux',
            'centos' => 'linux',
            'docker' => 'docker',
            'docker-compose' => 'docker',
            'kubernetes' => 'kubernetes',
            'laravel' => 'laravel',
            'php' => 'php',
            'wordpress' => 'wordpress',
            'node.js' => 'node',
            'npm' => 'node',
            'javascript' => 'javascript',
            'typescript' => 'typescript',
            'python' => 'python',
            'django' => 'python',
            'flask' => 'python',
            'aws' => 'aws',
            'amazon-ec2' => 'aws',
            's3' => 'aws',
            'lambda' => 'aws',
            'google-cloud' => 'gcp',
            'azure' => 'azure',
            'ssl' => 'ssl',
            'tls' => 'ssl',
            'https' => 'ssl',
            'security' => 'security',
            'oauth' => 'oauth',
            'jwt' => 'jwt',
            'authentication' => 'security',
            'redis' => 'redis',
            'mongodb' => 'database',
            'elasticsearch' => 'elasticsearch',
            'bash' => 'bash',
            'shell' => 'bash',
            'cron' => 'cron',
            'email' => 'email',
            'smtp' => 'email',
            'terraform' => 'terraform',
            'github-actions' => 'github-actions',
            'ci' => 'deployment',
            'deployment' => 'deployment',
            'nginx' => 'nginx',
            'load-balancing' => 'nginx',
            'reverse-proxy' => 'nginx',
            'graphql' => 'graphql',
            'api' => 'api',
            'rest' => 'api',
            'websocket' => 'websockets',
            'react' => 'react',
            'vue.js' => 'vue',
            'svelte' => 'svelte',
            'git' => 'deployment',
            'github' => 'deployment',
        ];

        foreach ($soTags as $tag) {
            $tag = strtolower($tag);
            if (isset($tagMap[$tag])) {
                return $tagMap[$tag];
            }
        }

        return 'general'; // Fallback
    }

    /**
     * Convert a CSV row (pre-processed SO export) into a CollectiveMind learning
     */
    protected function convertCsvRowToLearning(array $data): ?array
    {
        $title = $this->cleanHtml($data['Title'] ?? '');
        $questionBody = $this->cleanHtml($data['QuestionBody'] ?? '');
        $answerBody = $this->cleanHtml($data['AnswerBody'] ?? '');

        if (empty($title) || empty($answerBody)) {
            return null;
        }

        $tags = $this->parseTagsFromString($data['Tags'] ?? '');
        $category = $this->mapTagsToCategory($tags);

        // Build clean learning body — no SO attribution
        $learningBody = "## Problem\n\n{$questionBody}\n\n";
        $learningBody .= "## Solution\n\n{$answerBody}";

        return [
            'title' => $title,
            'body' => $learningBody,
            'category' => $category,
            'tags' => array_slice($tags, 0, 5),
            'source' => 'stack-overflow',
            'source_id' => $data['AnswerId'] ?? null,
            'source_score' => (int) ($data['AnswerScore'] ?? 0),
        ];
    }

    /**
     * Convert a Stack Overflow Q&A pair into a CollectiveMind learning (XML format)
     */
    protected function convertToLearning(array $question, array $answer): ?array
    {
        $title = $this->cleanHtml($question['title']);
        $body = $this->cleanHtml($question['body']);
        $answerBody = $this->cleanHtml($answer['Body'] ?? '');

        // Skip if answer body is too short
        if (strlen($answerBody) < 100) {
            return null;
        }

        // Build learning body — clean, agent-written style, no SO attribution visible
        $learningBody = "## Problem\n\n{$body}\n\n";
        $learningBody .= "## Solution\n\n{$answerBody}";

        // Metadata: category, tags, context (stored in DB, not shown to users)
        $category = $this->mapTagsToCategory($question['tags']);
        $tags = array_slice($question['tags'], 0, 5); // Max 5 tags

        return [
            'title' => $title,
            'body' => $learningBody,
            'category' => $category,
            'tags' => $tags,
            'source' => 'stack-overflow',
            'source_id' => $answer['Id'] ?? null,
            'source_score' => $answer['Score'] ?? 0,
        ];
    }

    /**
     * Clean HTML from Stack Overflow content
     */
    protected function cleanHtml(string $html): string
    {
        // Remove code blocks but keep content
        $html = preg_replace('/<code>(.*?)<\/code>/s', '`$1`', $html);
        $html = preg_replace('/<pre>(.*?)<\/pre>/s', "```\n$1\n```\n", $html);

        // Remove HTML tags but preserve text
        $html = strip_tags($html);

        // Decode HTML entities
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean up whitespace
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        $html = trim($html);

        return $html;
    }

    /**
     * Save imported learnings to database (with dedup)
     */
    protected function saveImportedLearnings(): void
    {
        $saved = 0;
        $skipped = 0;

        foreach ($this->soLearnings as $learningData) {
            // Check for duplicate by source
            if (Learning::where('source', 'stack-overflow')
                ->where('source_id', $learningData['source_id'])
                ->exists()) {
                $skipped++;
                continue;
            }

            // Check for very similar title (dedup)
            $similar = Learning::where('title', 'LIKE', '%' . substr($learningData['title'], 0, 50) . '%')
                ->where('category', $learningData['category'])
                ->exists();

            if ($similar) {
                $skipped++;
                continue;
            }

            // Assign to a random seed agent
            $agent = Agent::where('status', 'active')
                ->where('email', 'LIKE', '%@' . config('seed.email_domain', 'seed.collectivemind.dev'))
                ->inRandomOrder()
                ->first();

            if (!$agent) {
                $this->warn('No active seed agent found for assignment');
                break;
            }

            Learning::create([
                'agent_id' => $agent->id,
                'title' => $learningData['title'],
                'body' => $learningData['body'],
                'category' => $learningData['category'],
                'tags' => $learningData['tags'],
                'source' => $learningData['source'],
                'source_id' => $learningData['source_id'],
            ]);

            $saved++;
        }

        $this->info("Saved: {$saved} learnings");
        $this->info("Skipped (duplicates): {$skipped}");
    }

    /**
     * Post a learning to CollectiveMind via API
     */
    protected function postLearning(Agent $agent, array $learningData): void
    {
        $response = Http::withHeaders([
            'X-API-Key' => $agent->api_key,
            'Accept' => 'application/json',
        ])->post("{$this->baseUrl}/api/learnings", [
            'title' => $learningData['title'],
            'body' => $learningData['body'],
            'category' => $learningData['category'],
            'tags' => $learningData['tags'],
        ]);

        if ($response->successful()) {
            $this->totalPosted++;
        } else {
            $this->warn("Failed to post: {$learningData['title']} — {$response->status()}");
        }
    }

    /**
     * Verify a learning on CollectiveMind
     */
    protected function verifyLearning(Agent $agent, Learning $learning): void
    {
        $response = Http::withHeaders([
            'X-API-Key' => $agent->api_key,
            'Accept' => 'application/json',
        ])->post("{$this->baseUrl}/api/verify/{$learning->id}", [
            'status' => 'success',
            'context' => "Verified from Stack Overflow import. Source answer score: {$learning->source_score}",
        ]);

        if ($response->successful()) {
            $this->totalVerified++;
        } else {
            $this->warn("Failed to verify learning {$learning->id} — {$response->status()}");
        }
    }

    /**
     * Show sample learnings (for dry run)
     */
    protected function showSampleLearnings(): void
    {
        $samples = array_slice($this->soLearnings, 0, 5);

        $this->newLine();
        $this->info("Sample Learnings (first 5):");

        foreach ($samples as $i => $sample) {
            $this->newLine();
            $this->line("--- Learning " . ($i + 1) . " ---");
            $this->line("Title: {$sample['title']}");
            $this->line("Category: {$sample['category']}");
            $this->line("Tags: " . implode(', ', $sample['tags']));
            $this->line("Body preview: " . substr($sample['body'], 0, 200) . "...");
        }
    }
}
