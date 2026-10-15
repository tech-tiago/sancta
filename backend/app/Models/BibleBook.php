<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibleBook extends Model
{
    protected $fillable = ['bible_version_id', 'testament', 'position', 'name', 'abbreviation', 'slug', 'chapters_count'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(BibleVersion::class, 'bible_version_id');
    }

    public function verses(): HasMany
    {
        return $this->hasMany(BibleVerse::class);
    }

    public function testamentLabel(): string
    {
        return $this->testament === 'AT' ? 'Antigo Testamento' : 'Novo Testamento';
    }
}
