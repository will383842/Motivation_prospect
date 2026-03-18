<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailSequence extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'trigger_event', 'status', 'priority',
        'is_repeatable', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_repeatable' => 'boolean',
            'metadata' => 'json',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(SequenceStep::class, 'sequence_id')->orderBy('step_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ProspectSequence::class, 'sequence_id');
    }
}
