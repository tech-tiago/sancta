<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsSource extends Model
{
    use Auditable;

    protected $fillable = ['name', 'type', 'url', 'website', 'logo', 'is_active', 'fetch_interval_minutes', 'max_items'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_fetched_at' => 'datetime',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(NewsArticle::class);
    }

    public function isDue(): bool
    {
        return ! $this->last_fetched_at
            || $this->last_fetched_at->addMinutes(max(15, $this->fetch_interval_minutes))->isPast();
    }
}
