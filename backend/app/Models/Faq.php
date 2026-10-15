<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use Auditable;

    protected $fillable = ['question', 'answer', 'audience', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeFor(Builder $query, string $channel): Builder
    {
        return $query->where('is_active', true)
            ->whereIn('audience', [$channel, 'both'])
            ->orderBy('sort_order');
    }
}
