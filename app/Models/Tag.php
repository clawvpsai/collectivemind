<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'usage_count',
    ];

    protected $casts = [
        'usage_count' => 'integer',
    ];

    public static function findOrCreateByName(string $name): self
    {
        $name = strtolower(trim($name));

        return static::firstOrCreate(
            ['name' => $name],
            ['usage_count' => 0]
        );
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
