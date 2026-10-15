<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Par chave/valor editável no painel. Use o serviço App\Services\Settings
 * (com cache) em vez de consultar este model diretamente.
 */
class Setting extends Model
{
    use Auditable;

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
