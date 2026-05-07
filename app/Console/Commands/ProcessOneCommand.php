<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\Learning;
use Illuminate\Console\Command;

class ProcessOneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cm:seed:process-one
                            {--force : Force re-process even if already imported}
                            {--dry-run : Preview without saving}
                            {--so-dir= : Override SO data directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process a single Stack Overflow Q&A entry from the CSV file';

    protected string $csvPath;
    protected string $stateFile;

    public function handle(): int
    {
        $soDir = $this->option('so-dir') ?? storage_path('app/so-data');
        $this->csvPath = $soDir . '/so-export.csv';
        $this->stateFile = $soDir . '/processing_position.json';
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if (!file_exists($this->csvPath)) {
            $this->error("CSV not found: {$this->csvPath}");
            return self::FAILURE;
        }

        $currentPosition = $this->loadPosition();
        $this->info("Current position: {$currentPosition}");

        $result = $this->findAndProcessOneRow($currentPosition, $force, $dryRun);

        if ($result === false) {
            $this->warn("No more rows to process.");
            return self::SUCCESS;
        }

        [$position, $data, $learning] = $result;

        if ($dryRun) {
            $this->warn("DRY RUN — would save:");
            $this->line("  Title: {$data['Title']}");
            $this->line("  Category: {$learning['category']}");
            $this->line("  Tags: " . implode(', ', $learning['tags']));
            $this->line("  Answer score: {$data['AnswerScore']}");
        } else {
            $this->line("Saved [{$position}]: {$data['Title']}");
            $this->line("  Category: {$learning['category']} | Tags: " . implode(', ', $learning['tags']));
        }

        return self::SUCCESS;
    }

    /**
     * Iterate through CSV until we find a qualifying row.
     * Updates position after each skip so we don't re-scan.
     */
    protected function findAndProcessOneRow(int $skipToPosition, bool $force, bool $dryRun): ?array
    {
        $handle = fopen($this->csvPath, 'r');
        if (!$handle) {
            $this->error("Could not open CSV");
            return null;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            $this->error("Could not read CSV header");
            fclose($handle);
            return null;
        }

        $minAnswerScore = config('seed.so.min_answer_score', 3);
        $minAnswerLength = config('seed.so.min_answer_length', 100);
        $position = 0;
        $maxIterations = 50000; // Safety limit

        while (($row = fgetcsv($handle)) !== false && $maxIterations-- > 0) {
            $position++;

            if ($position <= $skipToPosition) {
                continue;
            }

            if (count($row) < count($header)) {
                $this->savePosition($position);
                continue;
            }

            $data = array_combine($header, $row);

            // Already imported check
            if (!$force && !empty($data['AnswerId'])) {
                $already = Learning::where('source', 'stack-overflow')
                    ->where('source_id', $data['AnswerId'])
                    ->exists();

                if ($already) {
                    $this->savePosition($position);
                    continue;
                }
            }

            // Quality checks
            if (empty($data['AnswerId']) || empty($data['AnswerBody'])) {
                $this->savePosition($position);
                continue;
            }

            $answerScore = (int) ($data['AnswerScore'] ?? 0);
            if ($answerScore < $minAnswerScore) {
                $this->savePosition($position);
                continue;
            }

            $answerBody = trim(strip_tags($data['AnswerBody'] ?? ''));
            if (strlen($answerBody) < $minAnswerLength) {
                $this->savePosition($position);
                continue;
            }

            $tags = $this->parseTagsFromString($data['Tags'] ?? '');
            $category = $this->mapTagsToCategory($tags);

            if (!in_array($category, Learning::CATEGORIES)) {
                $this->savePosition($position);
                continue;
            }

            // Found a qualifying row
            $learning = $this->convertCsvRowToLearning($data);
            $this->savePosition($position);
            fclose($handle);
            return [$position, $data, $learning];
        }

        fclose($handle);
        return null;
    }

    protected function saveLearning(array $learning, array $data): void
    {
        $agent = Agent::where('status', 'active')
            ->where('email', 'LIKE', '%@' . config('seed.email_domain', 'seed.collectivemind.dev'))
            ->inRandomOrder()
            ->first();

        if (!$agent) {
            $this->error("No active seed agent found. Run `cm:seed agents` first.");
            return;
        }

        Learning::create([
            'agent_id' => $agent->id,
            'title' => $learning['title'],
            'body' => $learning['body'],
            'category' => $learning['category'],
            'tags' => $learning['tags'],
            'source' => $learning['source'],
            'source_id' => $learning['source_id'],
            'source_score' => $learning['source_score'],
        ]);
    }

    protected function parseTagsFromString(string $tags): array
    {
        if (empty($tags)) {
            return [];
        }
        $tags = trim($tags, '<>');
        if (str_contains($tags, '<')) {
            return array_filter(explode('><', $tags));
        }
        return array_filter(array_map('trim', explode(',', $tags)));
    }

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
            'django' => 'django',
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
            'java' => 'java',
            'spring' => 'java',
            'spring-boot' => 'java',
            'kotlin' => 'kotlin',
            'android' => 'android',
            'swift' => 'swift',
            'ios' => 'ios',
            'flutter' => 'flutter',
            'dart' => 'dart',
            'rust' => 'rust',
            'golang' => 'golang',
            'c' => 'c',
            'cpp' => 'cpp',
            'csharp' => 'csharp',
            '.net' => 'csharp',
            'ruby' => 'ruby',
            'rails' => 'ruby',
            'elixir' => 'elixir',
            'haskell' => 'haskell',
            'perl' => 'perl',
            'scala' => 'scala',
            'powershell' => 'bash',
            'windows' => 'server',
            'macos' => 'server',
            'active-directory' => 'security',
            'encryption' => 'encryption',
            'backup' => 'backup',
            'monitoring' => 'monitoring',
            'logging' => 'logging',
            'prometheus' => 'monitoring',
            'grafana' => 'monitoring',
            'cost-optimization' => 'cost-optimization',
            'vps' => 'server',
            'cloudflare' => 'cloudflare',
            'dns' => 'dns',
            'domain' => 'dns',
        ];

        foreach ($soTags as $tag) {
            $tag = strtolower($tag);
            if (isset($tagMap[$tag])) {
                return $tagMap[$tag];
            }
        }

        return 'general';
    }

    protected function convertCsvRowToLearning(array $data): array
    {
        $title = $this->cleanHtml($data['Title'] ?? '');
        $questionBody = $this->cleanHtml($data['QuestionBody'] ?? '');
        $answerBody = $this->cleanHtml($data['AnswerBody'] ?? '');

        $tags = $this->parseTagsFromString($data['Tags'] ?? '');
        $category = $this->mapTagsToCategory($tags);

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

    protected function cleanHtml(string $html): string
    {
        $html = preg_replace('/<code>(.*?)<\/code>/s', '`$1`', $html);
        $html = preg_replace('/<pre>(.*?)<\/pre>/s', "```\n$1\n```\n", $html);
        $html = strip_tags($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        return trim($html);
    }

    protected function loadPosition(): int
    {
        if (file_exists($this->stateFile)) {
            $data = json_decode(file_get_contents($this->stateFile), true);
            return $data['position'] ?? 0;
        }
        return 0;
    }

    protected function savePosition(int $position): void
    {
        file_put_contents($this->stateFile, json_encode([
            'position' => $position,
            'updated_at' => now()->toIso8601String(),
        ]));
    }
}
