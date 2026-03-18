<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectSequence extends Model
{
    use HasUuids;

    protected $fillable = [
        'prospect_id', 'sequence_id', 'current_step_id', 'current_step_order',
        'status', 'exit_reason', 'sequence_version',
        'enrolled_at', 'next_step_at', 'completed_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'json',
            'enrolled_at' => 'datetime',
            'next_step_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(EmailSequence::class, 'sequence_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(SequenceStep::class, 'current_step_id');
    }
}
