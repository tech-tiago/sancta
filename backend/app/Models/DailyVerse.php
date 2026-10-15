<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DailyVerse extends Model
{
    use Auditable;

    protected $fillable = ['date', 'reference', 'text', 'reflection_title', 'reflection', 'image', 'reflection_is_premium'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'reflection_is_premium' => 'boolean',
        ];
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
