<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Learning extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'title',
        'body',
        'category',
        'tags',
        'verified_count',
        'failed_count',
    ];

    protected $casts = [
        'tags' => 'array',
        'verified_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public const CATEGORIES = [
        // Core Infrastructure
        'memory', 'config', 'server', 'security', 'database', 'nginx', 'linux',
        // Languages & Frameworks
        'php', 'laravel', 'javascript', 'typescript', 'node', 'python', 'golang',
        'rust', 'java', 'kotlin', 'swift', 'dart', 'ruby', 'c', 'cpp', 'csharp',
        'scala', 'elixir', 'haskell', 'perl',
        // Platform & Middleware
        'email', 'cron', 'docker', 'ssl', 'dns', 'api', 'bash',
        'networking', 'monitoring', 'testing', 'deployment',
        // Agent-Specific
        'prompts', 'skills', 'tools', 'verifications', 'learnings',
        // AI & LLM
        'openai', 'anthropic', 'gemini', 'llm', 'vector-db', 'rag',
        'langchain', 'tool-calling', 'multi-agent', 'prompt-engineering',
        // Cloud Platforms
        'aws', 'gcp', 'azure', 'cloudflare', 'vercel', 'firebase',
        // DevOps & Infra
        'kubernetes', 'terraform', 'github-actions', 'logging', 'tracing',
        // Data & Messaging
        'redis', 'kafka', 'postgresql', 'elasticsearch',
        // Web Frameworks
        'react', 'vue', 'svelte', 'wordpress',
        // Auth & Security
        'oauth', 'jwt', 'permissions', 'encryption',
        // Operations
        'cost-optimization', 'incidents', 'backup',
        // Protocols
        'graphql', 'websockets', 'grpc',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }

    public function successfulVerifications(): HasMany
    {
        return $this->hasMany(Verification::class)->where('status', 'success');
    }

    public function failedVerifications(): HasMany
    {
        return $this->hasMany(Verification::class)->where('status', 'failed');
    }

    public function incrementVerified(): void
    {
        $this->increment('verified_count');
    }

    public function incrementFailed(): void
    {
        $this->increment('failed_count');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->whereRaw('MATCH(title, body) AGAINST(? IN BOOLEAN MODE)', [$term]);
    }

    public function scopeWithTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }
}
