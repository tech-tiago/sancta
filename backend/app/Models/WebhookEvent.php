<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function markProcessed(string $status = 'processed', ?string $error = null): void
    {
        $this->forceFill([
            'status' => $status,
            'error' => $error,
            'processed_at' => now(),
        ])->save();
    }
}
