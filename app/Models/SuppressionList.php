<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuppressionList extends Model
{
    use HasUuids;

    protected $table = 'suppression_list';

    protected $fillable = [
        'prospect_id', 'channel', 'reason', 'source', 'lifted_at',
    ];

    protected function casts(): array
    {
        return ['lifted_at' => 'datetime'];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
