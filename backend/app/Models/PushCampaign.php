<?php

namespace App\Models;

use App\Enums\Audience;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushCampaign extends Model
{
    use Auditable;

    protected $fillable = ['title', 'body', 'image_url', 'deep_link', 'audience', 'scheduled_at'];

    protected function casts(): array
    {
        return [
            'audience' => Audience::class,
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canBeSent(): bool
    {
        return in_array($this->status, ['draft', 'scheduled', 'failed'], true);
    }
}
