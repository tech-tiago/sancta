<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações da plataforma editáveis no painel (Configurações → Plataforma).
 * Lidas com cache; qualquer alteração invalida o cache imediatamente.
 */
class Settings
{
    private const CACHE_KEY = 'sancta.settings';

    /** Valores padrão — também documentam todas as chaves existentes. */
    public const DEFAULTS = [
        // Identidade
        'app.name' => 'Sancta',
        'app.tagline' => 'Ora · Lê · Vive',
        'app.slogan' => 'Sua jornada de fé, todos os dias.',
        'app.support_email' => 'contato@sancta.app.br',
        'app.support_whatsapp' => '',
        'app.play_store_url' => 'https://play.google.com/store/apps/details?id=com.maria.app_igreja',
        'app.instagram' => '',
        'app.youtube' => '',

        // Controle de versão do app (força atualização)
        'app.min_version' => '3.0.0',
        'app.latest_version' => '3.0.0',
        'app.update_message' => 'Uma nova versão do Sancta está disponível com melhorias e correções.',
        'app.maintenance' => false,
        'app.maintenance_message' => 'Estamos realizando uma manutenção rápida. Volte em alguns minutos. 🙏',

        // Recursos (liga/desliga sem publicar nova versão)
        'features.ai_explanations' => true,
        'features.live' => true,
        'features.news' => true,
        'features.churches' => true,
        'features.intentions' => true,
        'features.events' => true,
        'features.guest_mode' => true,
        'features.web_bible' => true,

        // Tema padrão do app/site
        'theme.default' => 'system',

        // Oração do período na tela Hoje e no widget (ID de oração publicada; null = automático pelo
        // título/categoria — ver App\Services\Content\PeriodPrayers).
        'home.prayer_morning' => null,
        'home.prayer_afternoon' => null,
        'home.prayer_night' => null,

        // Assinatura (paywall do app e página /planos — ver App\Services\PremiumContent)
        'premium.name' => 'Sancta+',
        'premium.headline' => 'Aprofunde sua vida de oração',
        'premium.subheadline' => 'Orações guiadas, jornadas e reflexões para rezar todos os dias — no app e no site, com a mesma conta.',
        // Lista estruturada: ícone (chave de PremiumContent::ICONS), título e descrição.
        'premium.benefits' => [
            ['icon' => 'headphones', 'title' => 'Todas as orações guiadas, jornadas e novenas', 'description' => 'Reze com calma, passo a passo, onde estiver.'],
            ['icon' => 'sun', 'title' => 'Reflexão diária do Evangelho e homilia', 'description' => 'Uma meditação curta para cada dia da liturgia.'],
            ['icon' => 'sparkles', 'title' => 'Explicação de versículos com IA', 'description' => 'Entenda o contexto da Palavra quando surgir uma dúvida.'],
            ['icon' => 'book', 'title' => 'Planos de leitura da Bíblia completos', 'description' => 'Leia a Bíblia inteira no seu ritmo.'],
            ['icon' => 'calendar', 'title' => 'Eventos e retiros exclusivos', 'description' => 'Encontros online para rezar em comunidade.'],
            ['icon' => 'heart', 'title' => 'Sem anúncios — e você apoia a evangelização', 'description' => 'Sua assinatura mantém o Sancta no ar.'],
        ],
        'premium.social_proof' => '',
        'premium.guarantee' => 'Cancele quando quiser',
        'premium.faq' => [
            ['question' => 'Posso cancelar quando quiser?', 'answer' => 'Sim. No app, a assinatura é gerenciada pelo Google Play e pode ser cancelada a qualquer momento na Play Store. No site, os planos são pré-pagos e não renovam sozinhos.'],
            ['question' => 'Como funciona o teste grátis?', 'answer' => 'Nos planos com teste grátis, você usa o Sancta+ completo durante o período de teste e só é cobrado depois. Cancelando antes do fim do teste, nada é cobrado.'],
            ['question' => 'A assinatura vale no app e no site?', 'answer' => 'Sim. O acesso fica na sua conta: entre com o mesmo e-mail no app e no site.'],
            ['question' => 'Posso dar o Sancta+ de presente?', 'answer' => 'Sim. Em "Presentear Sancta+", no site, você compra um plano e recebe um código para enviar a quem quiser.'],
        ],

        // Notícias
        'news.open_in_app' => true,
    ];

    private ?array $values = null;

    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $stored = Cache::rememberForever(self::CACHE_KEY, function (): array {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Setting::query()->pluck('value', 'key')->all();
        });

        return $this->values = array_replace(self::DEFAULTS, $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default ?? (self::DEFAULTS[$key] ?? null));
    }

    public function bool(string $key): bool
    {
        return filter_var($this->get($key), FILTER_VALIDATE_BOOL);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->flush();
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS)) {
                Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }
        $this->flush();
    }

    public function flush(): void
    {
        $this->values = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** Descarta só a cópia em memória (processos longos, como a fila, voltam a ler do cache). */
    public function refresh(): void
    {
        $this->values = null;
    }
}
