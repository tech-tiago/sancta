<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\JourneyType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/** Jornadas: novenas, planos de leitura, desafios (ex.: "Quaresma com Sancta"). */
class Journey extends Model
{
    use Auditable, HasUniqueSlug, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'type', 'subtitle', 'description', 'cover_image', 'color', 'is_premium', 'is_featured',
        'status', 'available_from', 'available_until', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => JourneyType::class,
            'status' => ContentStatus::class,
            'is_premium' => 'boolean',
            'is_featured' => 'boolean',
            'available_from' => 'date',
            'available_until' => 'date',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(JourneyStep::class)->orderBy('position');
    }

    public function scopePublished(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('status', ContentStatus::Published->value)
            ->where(fn ($q) => $q->whereNull('available_from')->orWhere('available_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('available_until')->orWhere('available_until', '>=', $today));
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null;
    }
}
