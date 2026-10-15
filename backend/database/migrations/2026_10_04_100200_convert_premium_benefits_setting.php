<?php

use App\Services\PremiumContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `premium.benefits` passa de lista de strings para lista estruturada
 * [{icon, title, description}]. Converte o valor salvo no painel (se houver) sem perder textos.
 * A API continua enviando `benefits` como strings (compatibilidade com o app atual).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->transform(fn (array $value) => PremiumContent::normalizeBenefits($value));
    }

    public function down(): void
    {
        $this->transform(fn (array $value) => array_column(PremiumContent::normalizeBenefits($value), 'title'));
    }

    private function transform(callable $callback): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $row = DB::table('settings')->where('key', 'premium.benefits')->first();
        if (! $row) {
            return;
        }

        $value = json_decode((string) $row->value, true);
        if (! is_array($value)) {
            return;
        }

        DB::table('settings')->where('id', $row->id)->update([
            'value' => json_encode($callback($value), JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        Cache::forget('sancta.settings');
    }
};
