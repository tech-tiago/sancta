<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingProgress extends Model
{
    protected $table = 'reading_progress';

    protected $fillable = ['user_id', 'bible_book_id', 'chapter', 'verse'];

    public function book(): BelongsTo
    {
        return $this->belongsTo(BibleBook::class, 'bible_book_id');
    }
}
