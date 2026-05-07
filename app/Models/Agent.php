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

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function incrementTrustScore(int $amount = 1): void
    {
        $this->increment('trust_score', $amount);
        $this->checkBanThreshold();
    }

    public function decrementTrustScore(int $amount = 1): void
    {
        $this->decrement('trust_score', $amount);
        $this->checkBanThreshold();
    }

    public function addTrustPoints(int $amount): void
    {
        if ($amount === 0) {
            return;
        }
        if ($amount > 0) {
            $this->incrementTrustScore($amount);
        } else {
            $this->decrementTrustScore(abs($amount));
        }
    }

    public function getSuccessPoints(): int
    {
        return (int) env('TRUST_POINTS_SUCCESS', 2);
    }

    public function getFailedPoints(): int
    {
        return (int) env('TRUST_POINTS_FAILED', -2);
    }

    public function getVerifyPoints(): int
    {
        return (int) env('TRUST_POINTS_VERIFY', 1);
    }

    public function getBanThreshold(): int
    {
        return (int) env('TRUST_BAN_THRESHOLD', -20);
    }

    protected function checkBanThreshold(): void
    {
        $threshold = $this->getBanThreshold();
        if ($this->trust_score <= $threshold) {
            $this->suspend();
        }
    }

    public function suspend(): void
    {
        if ($this->status === 'suspended') {
            return;
        }

        // Delete all verifications first (foreign key integrity)
        $this->verifications()->delete();

        // Delete all learnings (this also deletes related verifications via model events)
        $this->learnings()->delete();

        $this->update(['status' => 'suspended', 'trust_score' => 0]);
    }

    public function recalculateTrustScore(): void
    {
        $successPoints = $this->getSuccessPoints();
        $failedPoints = $this->getFailedPoints();
        $verifyPoints = $this->getVerifyPoints();

        $total = 0;

        // Points earned when OTHER agents verified this agent's learnings
        $learnings = $this->learnings()->with('verifications')->get();
        foreach ($learnings as $learning) {
            foreach ($learning->verifications as $v) {
                if ($v->pivot->agent_id === $this->id) {
                    continue; // skip self-verifications
                }
                $total += $v->status === 'success' ? $successPoints : $failedPoints;
            }
        }

        // Points earned when THIS agent verified others' learnings
        foreach ($this->verifications as $v) {
            $total += $verifyPoints;
        }

        $this->update(['trust_score' => $total]);
    }
}
