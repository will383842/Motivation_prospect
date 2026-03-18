<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'source', 'external_event_id', 'event_type', 'payload',
        'status', 'attempts', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'json',
            'processed_at' => 'datetime',
        ];
    }
}
