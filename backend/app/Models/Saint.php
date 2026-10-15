<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Saint extends Model
{
    use Auditable, HasUniqueSlug;

    protected string $slugSource = 'name';

    protected $fillable = ['name', 'slug', 'feast_month', 'feast_day', 'title', 'biography', 'prayer', 'image', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
