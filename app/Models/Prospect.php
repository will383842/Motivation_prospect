<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prospect extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'email', 'first_name', 'last_name', 'phone', 'country', 'language', 'timezone',
        'source', 'source_detail', 'job_title', 'company', 'tags', 'extra',
        'lifecycle_state', 'lifecycle_changed_at',
        'emails_sent', 'emails_opened', 'emails_clicked', 'emails_bounced',
        'engagement_score', 'fatigue_score', 'fatigue_multiplier',
        'join_code', 'firebase_uid', 'converted_at', 'converted_via',
        'import_batch_id', 'is_active', 'is_sos_expat_user', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'json',
            'extra' => 'json',
            'is_active' => 'boolean',
            'is_sos_expat_user' => 'boolean',
            'lifecycle_changed_at' => 'datetime',
            'converted_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'fatigue_score' => 'float',
            'fatigue_multiplier' => 'float',
            'engagement_score' => 'float',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProspectEvent::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(ProspectSequence::class);
    }

    public function suppressions(): HasMany
    {
        return $this->hasMany(SuppressionList::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(ConsentRecord::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: $this->email;
    }

    public function isConverted(): bool
    {
        return $this->lifecycle_state === 'converted';
    }

    public function isSuppressed(): bool
    {
        return $this->suppressions()
            ->where('channel', 'email')
            ->whereNull('lifted_at')
            ->exists();
    }
}
