<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BibleVerse extends Model
{
    public $timestamps = false;

    protected $fillable = ['bible_book_id', 'chapter', 'verse', 'text'];

    public function book(): BelongsTo
    {
        return $this->belongsTo(BibleBook::class, 'bible_book_id');
    }

    public function reference(): string
    {
        return "{$this->book->abbreviation} {$this->chapter},{$this->verse}";
    }
}
