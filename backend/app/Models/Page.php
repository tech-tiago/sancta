<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Html;
use Illuminate\Database\Eloquent\Model;

/** Páginas institucionais: termos, privacidade, sobre, exclusão de conta... */
class Page extends Model
{
    use Auditable;

    protected $fillable = ['slug', 'title', 'content', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Page $page): void {
            if ($page->isDirty('content') && $page->content !== null) {
                $page->content = Html::sanitize($page->content);
            }
        });
    }
}
