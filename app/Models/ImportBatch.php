<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'filename', 'source', 'source_detail',
        'total_rows', 'imported', 'duplicates_skipped', 'sos_users_skipped', 'invalid_skipped',
        'status', 'errors', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'json',
            'processed_at' => 'datetime',
        ];
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }
}
