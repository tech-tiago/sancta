<?php

namespace App\Models;

use App\Enums\ChurchType;
use App\Enums\ContentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUniqueSlug;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Church extends Model
{
    use Auditable, HasUniqueSlug, HasUuid, SoftDeletes;

    protected string $slugSource = 'name';

    protected $fillable = [
        'name', 'slug', 'type', 'diocese', 'patron', 'description', 'address', 'neighborhood', 'city', 'state',
        'country', 'postal_code', 'latitude', 'longitude', 'phone', 'whatsapp', 'email', 'website', 'instagram',
        'facebook', 'youtube', 'cover_image', 'is_verified', 'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChurchType::class,
            'status' => ContentStatus::class,
            'is_verified' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Church $church): void {
            if ($church->instagram) {
                $church->instagram = '@'.ltrim(trim($church->instagram), '@');
            }
            if ($church->state) {
                $church->state = strtoupper($church->state);
            }
        });
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ChurchSchedule::class)->orderBy('type')->orderBy('weekday')->orderBy('starts_at');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ChurchSubmission::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'church_favorites');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value);
    }

    /**
     * Filtra por raio (km) usando caixa delimitadora + fórmula de Haversine.
     * Valores numéricos são validados no controller e passados por binding.
     */
    public function scopeNear(Builder $query, float $lat, float $lng, float $radiusKm = 50): Builder
    {
        $latDelta = $radiusKm / 111.045;
        $lngDelta = $radiusKm / (111.045 * max(cos(deg2rad($lat)), 0.01));

        $haversine = '(6371 * acos(least(1, greatest(-1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))))';

        return $query
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->select('churches.*')
            ->selectRaw("{$haversine} as distance_km", [$lat, $lng, $lat])
            ->orderBy('distance_km');
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null;
    }

    public function fullAddress(): string
    {
        return collect([$this->address, $this->neighborhood, $this->city ? "{$this->city}/{$this->state}" : null])
            ->filter()->implode(' — ');
    }

    public function mapsUrl(): ?string
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination='.$this->latitude.','.$this->longitude;
    }
}
