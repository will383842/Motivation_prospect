<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'prospect_id', 'template_slug', 'subject', 'status',
        'mailwizz_message_id', 'metadata',
        'sent_at', 'opened_at', 'clicked_at', 'bounced_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'json',
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'bounced_at' => 'datetime',
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
