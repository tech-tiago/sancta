<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementInteraction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
