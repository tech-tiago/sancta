<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibleVersion extends Model
{
    protected $fillable = ['name', 'abbreviation', 'language', 'license_notice', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function books(): HasMany
    {
        return $this->hasMany(BibleBook::class)->orderBy('position');
    }

    public static function default(): ?self
    {
        return cache()->remember('bible.default_version', 3600, fn () => static::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first());
    }
}
