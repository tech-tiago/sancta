<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    protected $fillable = ['fcm_token', 'platform', 'app_version', 'device_model', 'notifications_enabled', 'last_seen_at'];

    protected $hidden = ['fcm_token'];

    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
