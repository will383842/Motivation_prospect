<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SequenceStep extends Model
{
    use HasUuids;

    protected $fillable = [
        'sequence_id', 'step_order', 'type', 'template_slug', 'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'json',
        ];
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(EmailSequence::class, 'sequence_id');
    }
}
