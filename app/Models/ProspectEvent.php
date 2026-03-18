<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectEvent extends Model
{
    use HasUuids;

    protected $fillable = ['prospect_id', 'event_type', 'event_data', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'event_data' => 'json',
            'occurred_at' => 'datetime',
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
