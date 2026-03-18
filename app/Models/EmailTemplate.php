<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    use HasUuids;

    protected $fillable = ['slug', 'name', 'category', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(EmailTemplateVariant::class, 'template_id');
    }
}
