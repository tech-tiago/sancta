<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BibleAnnotation extends Model
{
    public const COLORS = ['gold', 'blue', 'green', 'rose', 'lilac'];

    protected $fillable = ['bible_verse_id', 'highlight_color', 'note', 'is_bookmarked'];

    protected function casts(): array
    {
        return ['is_bookmarked' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verse(): BelongsTo
    {
        return $this->belongsTo(BibleVerse::class, 'bible_verse_id');
    }

    public function isEmpty(): bool
    {
        return ! $this->highlight_color && blank($this->note) && ! $this->is_bookmarked;
    }
}
