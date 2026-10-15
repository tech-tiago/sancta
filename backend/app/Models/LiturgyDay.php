<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class LiturgyDay extends Model
{
    use Auditable;

    protected $fillable = ['date', 'title', 'color', 'readings', 'prayers', 'homily', 'homily_author', 'is_locked', 'source', 'fetched_at'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'readings' => 'array',
            'prayers' => 'array',
            'is_locked' => 'boolean',
            'fetched_at' => 'datetime',
        ];
    }

    /** Cor litúrgica → hexadecimal para o app/site. */
    public function colorHex(): string
    {
        return match (mb_strtolower((string) $this->color)) {
            'verde' => '#2E7D4F',
            'vermelho' => '#B3261E',
            'roxo', 'violeta' => '#6B3FA0',
            'rosa', 'róseo' => '#D97BA6',
            'branco' => '#F4EBD0',
            'preto' => '#1A1A1A',
            'azul' => '#2F5DA8',
            default => '#C9A45C',
        };
    }
}
