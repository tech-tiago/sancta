<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gera automaticamente um slug único a partir de um atributo (title/name).
 * Defina `protected string $slugSource = 'title';` no model.
 */
trait HasUniqueSlug
{
    protected static function bootHasUniqueSlug(): void
    {
        static::saving(function ($model): void {
            $source = $model->slugSource ?? 'title';

            if (blank($model->slug) && filled($model->{$source})) {
                $model->slug = static::uniqueSlug((string) $model->{$source}, $model->getKey());
            } elseif ($model->isDirty('slug') && filled($model->slug)) {
                $model->slug = static::uniqueSlug((string) $model->slug, $model->getKey());
            }
        });
    }

    public static function uniqueSlug(string $value, mixed $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($value), 180, '') ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 2;

        $query = fn (string $candidate) => static::query()
            ->when(method_exists(static::class, 'bootSoftDeletes'), fn ($q) => $q->withTrashed())
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        while ($query($slug)) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
