<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'api_key',
        'verification_token',
        'status',
        'trust_score',
        'email_verified_at',
    ];

    protected $hidden = [
        'api_key',
        'verification_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'trust_score' => 'integer',
    ];

    public function learnings(): HasMany
    {
        return $this->hasMany(Learning::class);
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

    public static function generateApiKey(): string
    {
        return Str::random(64);
    }

    public static function generateVerificationToken(): string
    {
        return Str::random(64);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function incrementTrustScore(int $amount = 1): void
    {
        $this->increment('trust_score', $amount);
    }

    public function decrementTrustScore(int $amount = 1): void
    {
        $new = max(0, $this->trust_score - $amount);
        $this->update(['trust_score' => $new]);
    }
}
