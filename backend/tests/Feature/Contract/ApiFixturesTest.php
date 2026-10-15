<?php

namespace Tests\Feature\Contract;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Announcement;
use App\Models\Church;
use App\Models\Event;
use App\Models\LiturgyDay;
use App\Models\LiveChannel;
use App\Models\NewsArticle;
use App\Models\NewsSource;
use App\Models\Plan;
use App\Models\Prayer;
use App\Models\PrayerIntention;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\Gateways\GatewayPayment;
use App\Services\Billing\GooglePlayBilling;
use Carbon\CarbonImmutable;
use Database\Seeders\BibleSeeder;
use Database\Seeders\ChurchSeeder;
use Database\Seeders\DailyVerseSeeder;
use Database\Seeders\JourneySeeder;
use Database\Seeders\MediaSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\PrayerSeeder;
use Database\Seeders\SaintSeeder;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use stdClass;
use Tests\Feature\Security\Concerns\GoogleTestKeys;
use Tests\TestCase;

/**
 * Contrato API ↔ app Flutter.
 *
 * Monta dados realistas (os seeders do projeto + registros de teste), chama cada endpoint que o app usa
 * (como visitante, usuário gratuito e assinante) e confere a estrutura das respostas — sempre. Com
 * `SANCTA_WRITE_FIXTURES=1`, grava cada resposta em `app/test/fixtures/api/<nome>.json`, que o teste
 * `app/test/contract_test.dart` lê com os modelos reais do app.
 *
 * Regerar as fixtures (pasta `backend/`):
 *     SANCTA_WRITE_FIXTURES=1 php artisan test --filter=ApiFixturesTest
 * e depois, na pasta `app/`: `flutter test test/contract_test.dart`.
 *
 * Nada sai para a rede: liturgia, Google (login, Play, JWKS) e Gemini são simulados.
 */
class ApiFixturesTest extends TestCase
{
    use GoogleTestKeys, RefreshDatabase;

    private const ROOT = 'https://api.sancta.test';

    private const GOOGLE_CLIENT_ID = '123-sancta.apps.googleusercontent.com';

    private const PACKAGE = 'com.maria.app_igreja';

    /** @var array<int, string> tokens por usuário */
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Domingo, 04/10/2026, 8h30 em Brasília (a mesma cena dos goldens do app).
        $this->travelTo(CarbonImmutable::parse('2026-10-04 08:30:00', 'America/Sao_Paulo'));

        config([
            // Chave fixa: nada derivado da APP_KEY real vai para as fixtures (ex.: id ofuscado do Google Play).
            'app.key' => 'base64:'.base64_encode(str_repeat('s', 32)),
            'app.url' => self::ROOT,
            'services.google.web_client_id' => self::GOOGLE_CLIENT_ID,
            'services.google.client_ids' => [],
            'services.google_play.package_name' => self::PACKAGE,
            'services.google_play.service_account' => $this->serviceAccountJson(),
            'services.gemini.api_key' => 'chave-de-teste',
            'sancta.liturgy.api_url' => 'https://liturgia.invalid',
        ]);
        URL::forceRootUrl(self::ROOT);
        URL::forceScheme('https');
        // As requisições vão para o host acima: limpa hosts confiáveis estáticos deixados por outro teste.
        Request::setTrustedHosts([]);
        Storage::fake('public', ['url' => self::ROOT.'/storage']);
        Notification::fake();

        Http::preventStrayRequests();
        Http::fake($this->fakeGoogleJwks() + [
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.teste', 'expires_in' => 3600]),
            'androidpublisher.googleapis.com/*:acknowledge' => Http::response([], 200),
            'androidpublisher.googleapis.com/*' => fn () => Http::response($this->playPurchase),
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Jesus explica a Nicodemos que é preciso nascer de novo, pela água e pelo Espírito.']]]]]]),
        ]);

        $this->seedRealisticData();
    }

    /** Resposta simulada da Google Play Developer API (subscriptionsv2) para o próximo verify. */
    private array $playPurchase = [];

    // ───────────────────────── Visitante ─────────────────────────

    public function test_public_endpoints_for_guests(): void
    {
        $this->record('config_visitante', 'GET', '/config', $this->api('GET', '/config'), [
            'app' => ['name', 'tagline', 'slogan', 'min_version', 'latest_version', 'maintenance', 'play_store_url', 'support_email', 'privacy_url', 'terms_url', 'delete_account_url'],
            'features' => ['ai_explanations', 'live', 'news', 'churches', 'intentions', 'events', 'guest_mode'],
            'theme' => ['default'],
            'maps' => ['tiles_light', 'tiles_dark', 'dark_filter', 'attribution'],
            'premium' => ['name', 'headline', 'subheadline', 'benefits', 'benefit_items' => ['*' => ['icon', 'title', 'description']], 'social_proof', 'guarantee', 'faq' => ['*' => ['question', 'answer']], 'testimonials' => ['*' => ['author', 'content', 'rating']]],
            'billing' => ['obfuscated_account_id', 'product_ids'],
            'auth' => ['google_server_client_id'],
        ])->assertJsonPath('billing.obfuscated_account_id', null);

        $home = $this->record('home_visitante', 'GET', '/home', $this->api('GET', '/home'), self::HOME);
        $home->assertJsonPath('continue_journey', null)->assertJsonPath('is_premium', false)
            ->assertJsonPath('period_prayers.morning.slug', 'oracao-da-manha')
            ->assertJsonPath('period_prayers.afternoon.slug', 'angelus')
            ->assertJsonPath('period_prayers.night.slug', 'oracao-da-noite')
            ->assertJsonPath('saint.name', 'São Francisco de Assis')
            ->assertJsonPath('liturgy.gospel_reference', 'Mt 21,33-43')
            ->assertJsonPath('daily_verse.reflection_locked', false);
        $this->assertNotEmpty($home->json('live_now'));
        $this->assertNotEmpty($home->json('events'));

        $this->record('liturgia_visitante', 'GET', '/liturgy?date={data}', $this->api('GET', '/liturgy?date=2026-10-04'), self::LITURGY)
            ->assertJsonPath('homily', null)->assertJsonPath('homily_locked', true);
        $this->assertJsonObject($this->record('liturgia_sem_oracoes', 'GET', '/liturgy?date={data}', $this->api('GET', '/liturgy?date=2026-10-05'), self::LITURGY), 'prayers');

        $books = $this->record('biblia_livros', 'GET', '/bible/books', $this->api('GET', '/bible/books'), [
            'version' => ['name', 'abbreviation', 'license_notice'],
            'books' => ['*' => ['testament', 'position', 'name', 'abbreviation', 'slug', 'chapters_count']],
        ]);
        $this->assertCount(73, $books->json('books'));
        $this->record('biblia_capitulo_visitante', 'GET', '/bible/books/{livro}/chapters/{n}', $this->api('GET', '/bible/books/salmos/chapters/116'), self::CHAPTER)
            ->assertJsonPath('verses.0.highlight', null);
        $this->record('biblia_busca', 'GET', '/bible/search?q={termo}', $this->api('GET', '/bible/search?q=Nicodemos'), [
            'query', 'results' => ['*' => ['id', 'reference', 'book', 'book_slug', 'chapter', 'verse', 'text']],
        ]);

        $this->record('categorias', 'GET', '/categories', $this->api('GET', '/categories'), ['*' => ['slug', 'name', 'description', 'icon', 'color', 'prayers_count']]);
        $this->record('oracoes', 'GET', '/prayers', $this->api('GET', '/prayers'), ['data' => ['*' => self::PRAYER], 'links', 'meta']);
        $this->record('oracoes_categoria', 'GET', '/prayers?category={slug}', $this->api('GET', '/prayers?category=manha-e-noite'), ['data' => ['*' => self::PRAYER]]);
        $this->record('oracao_visitante', 'GET', '/prayers/{slug}', $this->api('GET', '/prayers/oracao-da-manha'), self::PRAYER_DETAIL)
            ->assertJsonPath('locked', false)->assertJsonPath('is_favorite', false);
        $liturgical = Prayer::query()->where('title', 'Oração Eucarística para Missas com Crianças III')->value('slug');
        $this->record('oracao_liturgica', 'GET', '/prayers/{slug}', $this->api('GET', "/prayers/{$liturgical}"), self::PRAYER_DETAIL);
        $this->record('oracao_bloqueada_visitante', 'GET', '/prayers/{slug}', $this->api('GET', '/prayers/'.$this->premiumPrayer()->slug), self::PRAYER_DETAIL)
            ->assertJsonPath('locked', true)->assertJsonPath('audio_url', null);

        $this->record('jornadas_visitante', 'GET', '/journeys', $this->api('GET', '/journeys'), ['*' => self::JOURNEY]);
        $this->record('jornada_visitante', 'GET', '/journeys/{slug}', $this->api('GET', '/journeys/novena-nossa-senhora-aparecida'), self::JOURNEY_DETAIL);

        $this->record('igrejas', 'GET', '/churches', $this->api('GET', '/churches?limit=300'), ['*' => self::CHURCH]);
        $this->record('igrejas_proximas', 'GET', '/churches?lat=&lng=&radius=', $this->api('GET', '/churches?lat=-16.45&lng=-39.07&radius=80&limit=300'), ['*' => self::CHURCH]);
        $this->record('igrejas_busca', 'GET', '/churches?q=', $this->api('GET', '/churches?q=Par%C3%B3quia&limit=300'), ['*' => self::CHURCH]);
        $church = Church::query()->whereHas('schedules')->orderBy('id')->firstOrFail();
        $detail = $this->record('igreja', 'GET', '/churches/{uuid}', $this->api('GET', "/churches/{$church->uuid}"), self::CHURCH_DETAIL);
        $this->assertJsonObject($detail, 'schedules');
        $this->assertJsonObject($detail, 'contacts');
        $bare = $this->record('igreja_sem_horarios', 'GET', '/churches/{uuid}', $this->api('GET', '/churches/'.Church::query()->where('name', 'Capela São José')->value('uuid')), self::CHURCH_DETAIL);
        $this->assertJsonObject($bare, 'schedules');
        $this->assertJsonObject($bare, 'contacts');

        $this->record('noticias', 'GET', '/news', $this->api('GET', '/news'), ['data' => ['*' => ['id', 'slug', 'title', 'summary', 'image_url', 'source', 'url', 'is_featured', 'published_at']], 'links', 'meta']);
        $this->record('noticia', 'GET', '/news/{id}', $this->api('GET', '/news/'.NewsArticle::query()->whereNotNull('content')->value('id')), ['id', 'title', 'source', 'published_at', 'author', 'blocks' => ['*' => ['type', 'text']], 'has_full_content']);
        $this->record('ao_vivo', 'GET', '/live-channels', $this->api('GET', '/live-channels'), ['*' => [
            'id', 'name', 'description', 'category', 'category_label', 'cover_url', 'logo_url', 'youtube_url', 'watch_url', 'is_live', 'live', 'upcoming',
            'on_schedule_now', 'current_schedule_title', 'schedule', 'next_at', 'topic', 'type', 'url', 'video_id', 'fallback_url', 'thumbnail_url',
        ]])->assertJsonPath('0.is_live', true);
        $this->record('intencoes_visitante', 'GET', '/intentions', $this->api('GET', '/intentions'), ['data' => ['*' => ['id', 'text', 'author', 'prayers_count', 'prayed', 'created_at']], 'meta']);
        $this->record('planos', 'GET', '/plans', $this->api('GET', '/plans'), ['*' => self::PLAN])->assertJsonCount(3);
        $this->record('pagina', 'GET', '/pages/{slug}', $this->api('GET', '/pages/sobre'), ['slug', 'title', 'blocks' => ['*' => ['type', 'text']], 'updated_at']);
        $this->record('faqs', 'GET', '/faqs', $this->api('GET', '/faqs'), ['*' => ['question', 'answer']]);

        $announcements = $this->record('avisos_visitante', 'GET', '/announcements', $this->api('GET', '/announcements'), ['*' => self::ANNOUNCEMENT]);
        $this->record('aviso_interacao', 'POST', '/announcements/{id}/interactions', $this->api('POST', '/announcements/'.$announcements->json('0.id').'/interactions', null, ['action' => 'view']), ['ok']);
    }

    // ───────────────────────── Conta gratuita ─────────────────────────

    public function test_account_endpoints_for_a_free_user(): void
    {
        $register = $this->record('auth_cadastro', 'POST', '/auth/register', $this->api('POST', '/auth/register', null, [
            'name' => 'Ana Clara Souza', 'email' => 'ana.clara@exemplo.com', 'password' => 'SenhaForte2026', 'device_name' => 'Android',
            'accept_terms' => true, 'marketing_opt_in' => false,
        ])->assertCreated(), self::AUTH);
        $register->assertJsonPath('user.is_premium', false)->assertJsonPath('user.subscription', null);

        $this->record('auth_login', 'POST', '/auth/login', $this->api('POST', '/auth/login', null, [
            'email' => 'ana.clara@exemplo.com', 'password' => 'SenhaForte2026', 'device_name' => 'Android',
        ])->assertOk(), self::AUTH);

        $idToken = $this->googleJwt(['iss' => 'https://accounts.google.com', 'aud' => self::GOOGLE_CLIENT_ID, 'sub' => 'google-123', 'email' => 'joao.google@exemplo.com', 'email_verified' => true, 'name' => 'João Pedro']);
        $this->record('auth_google', 'POST', '/auth/google', $this->api('POST', '/auth/google', null, ['id_token' => $idToken, 'device_name' => 'Android']), self::AUTH)
            ->assertJsonPath('user.has_password', false);
        $this->record('auth_esqueci_senha', 'POST', '/auth/forgot-password', $this->api('POST', '/auth/forgot-password', null, ['email' => 'ana.clara@exemplo.com'])->assertOk(), ['message']);

        $ana = User::query()->where('email', 'ana.clara@exemplo.com')->firstOrFail();

        $this->record('config_gratuito', 'GET', '/config', $this->api('GET', '/config', $ana), ['billing' => ['obfuscated_account_id']])
            ->assertJsonPath('billing.obfuscated_account_id', GooglePlayBilling::obfuscatedAccountId($ana));
        $this->record('me_gratuito', 'GET', '/me', $this->api('GET', '/me', $ana), self::USER)->assertJsonPath('is_premium', false);
        $updated = $this->record('me_atualizar', 'PATCH', '/me', $this->api('PATCH', '/me', $ana, [
            'theme' => 'sepia',
            'notification_preferences' => ['daily_reminder' => true, 'reminder_time' => '06:30', 'evening_reminder' => true, 'evening_reminder_time' => '21:00', 'liturgy' => true, 'news' => false, 'churches' => true],
        ]), self::USER);
        $updated->assertJsonPath('theme', 'sepia')->assertJsonPath('notification_preferences.evening_reminder_time', '21:00');
        $this->assertJsonObject($updated, 'notification_preferences');
        $this->record('me_atualizar_nome', 'PATCH', '/me', $this->api('PATCH', '/me', $ana, ['name' => 'Ana Clara']), self::USER)->assertJsonPath('first_name', 'Ana');
        // Preferências vazias continuam sendo um mapa (o app lê `notification_preferences` como objeto).
        $this->assertJsonObject($this->api('PATCH', '/me', $ana, ['notification_preferences' => []])->assertOk(), 'notification_preferences');
        $this->api('PATCH', '/me', $ana, ['notification_preferences' => ['daily_reminder' => true, 'reminder_time' => '06:30', 'evening_reminder' => true, 'evening_reminder_time' => '21:00', 'liturgy' => true, 'news' => false, 'churches' => true]])->assertOk();

        // Bíblia: progresso (vazio e salvo), anotações e capítulo com as anotações do usuário.
        $this->record('biblia_progresso_vazio', 'GET', '/me/bible/progress', $this->api('GET', '/me/bible/progress', $ana)->assertOk(), []);
        $this->record('biblia_progresso_salvar', 'PUT', '/me/bible/progress', $this->api('PUT', '/me/bible/progress', $ana, ['book' => 'joao', 'chapter' => 3]), ['message']);
        $this->record('biblia_progresso', 'GET', '/me/bible/progress', $this->api('GET', '/me/bible/progress', $ana), ['book_slug', 'book', 'chapter', 'verse', 'updated_at'])
            ->assertJsonPath('book_slug', 'joao');
        $verses = $this->api('GET', '/bible/books/salmos/chapters/116')->json('verses');
        $this->record('biblia_anotar', 'PUT', '/me/bible/annotations', $this->api('PUT', '/me/bible/annotations', $ana, [
            'verse_ids' => [$verses[0]['id']], 'highlight' => 'gold', 'note' => 'Louvar com toda a família.', 'bookmarked' => true,
        ]), ['message']);
        $this->record('biblia_capitulo_gratuito', 'GET', '/bible/books/{livro}/chapters/{n}', $this->api('GET', '/bible/books/salmos/chapters/116', $ana), self::CHAPTER)
            ->assertJsonPath('verses.0.highlight', 'gold')->assertJsonPath('verses.0.bookmarked', true);
        foreach (['notes' => 'biblia_anotacoes_notas', 'highlights' => 'biblia_anotacoes_destaques', 'bookmarks' => 'biblia_anotacoes_marcadores'] as $type => $name) {
            $this->record($name, 'GET', '/me/bible/annotations?type={tipo}', $this->api('GET', "/me/bible/annotations?type={$type}", $ana), [
                'data' => ['*' => ['verse_id', 'reference', 'book_slug', 'chapter', 'verse', 'text', 'highlight', 'note', 'bookmarked', 'updated_at']], 'meta',
            ]);
        }
        $this->record('biblia_explicar_gratuito', 'POST', '/bible/explain', $this->api('POST', '/bible/explain', $ana, ['verse_ids' => [$verses[0]['id']]])->assertStatus(402), ['message', 'paywall'])
            ->assertJsonPath('paywall', true);

        // Orações, igrejas e jornadas.
        $this->record('oracao_favoritar', 'POST', '/prayers/{slug}/favorite', $this->api('POST', '/prayers/oracao-da-manha/favorite', $ana), ['is_favorite'])->assertJsonPath('is_favorite', true);
        $this->record('oracoes_favoritas', 'GET', '/me/favorite-prayers', $this->api('GET', '/me/favorite-prayers', $ana), ['*' => self::PRAYER]);
        $this->record('oracao_bloqueada_gratuito', 'GET', '/prayers/{slug}', $this->api('GET', '/prayers/'.$this->premiumPrayer()->slug, $ana), self::PRAYER_DETAIL)->assertJsonPath('locked', true);
        $church = Church::query()->whereHas('schedules')->orderBy('id')->firstOrFail();
        $this->record('igreja_favoritar', 'POST', '/churches/{uuid}/favorite', $this->api('POST', "/churches/{$church->uuid}/favorite", $ana), ['is_favorite']);
        $this->record('igrejas_favoritas', 'GET', '/me/favorite-churches', $this->api('GET', '/me/favorite-churches', $ana), ['*' => self::CHURCH]);
        $this->record('igreja_gratuito', 'GET', '/churches/{uuid}', $this->api('GET', "/churches/{$church->uuid}", $ana), self::CHURCH_DETAIL)->assertJsonPath('is_favorite', true);

        $this->record('jornada_concluir_dia', 'POST', '/journeys/{slug}/steps/{n}/complete', $this->api('POST', '/journeys/novena-nossa-senhora-aparecida/steps/1/complete', $ana), ['completed_steps', 'steps_count', 'finished']);
        $this->record('jornadas_gratuito', 'GET', '/journeys', $this->api('GET', '/journeys', $ana), ['*' => self::JOURNEY]);
        $this->record('jornada_gratuito', 'GET', '/journeys/{slug}', $this->api('GET', '/journeys/novena-nossa-senhora-aparecida', $ana), self::JOURNEY_DETAIL)
            ->assertJsonPath('steps.0.completed', true);
        $this->record('jornada_bloqueada_gratuito', 'GET', '/journeys/{slug}', $this->api('GET', '/journeys/evangelho-de-joao-em-21-dias', $ana), self::JOURNEY_DETAIL)
            ->assertJsonPath('locked', true)->assertJsonPath('steps.1.locked', true);
        $this->record('oracao_sessao', 'POST', '/me/prayer-sessions', $this->api('POST', '/me/prayer-sessions', $ana, ['content_type' => 'prayer', 'content_id' => Prayer::query()->where('slug', 'oracao-da-manha')->value('id'), 'seconds' => 240])->assertCreated(), ['current_streak', 'longest_streak', 'total_prayer_minutes']);
        $this->record('me_estatisticas', 'GET', '/me/stats', $this->api('GET', '/me/stats', $ana), ['current_streak', 'longest_streak', 'total_prayer_minutes', 'sessions_count', 'journeys_completed_steps', 'last_seven_days' => ['*' => ['date', 'seconds']]]);
        $this->record('home_gratuito', 'GET', '/home', $this->api('GET', '/home', $ana), self::HOME)
            ->assertJsonPath('continue_journey.slug', 'novena-nossa-senhora-aparecida')->assertJsonPath('streak', 1);

        // Comunidade.
        $this->record('igreja_sugestao', 'POST', '/church-submissions', $this->api('POST', '/church-submissions', $ana, [
            'name' => 'Paróquia Nossa Senhora da Pena', 'type' => 'paroquia', 'address' => 'Praça da Matriz, 1', 'city' => 'Porto Seguro', 'state' => 'BA',
            'latitude' => -16.4435, 'longitude' => -39.0643, 'mass_times' => 'Domingo 8h e 19h',
        ])->assertCreated(), ['id', 'message']);
        $this->record('intencao_enviar', 'POST', '/intentions', $this->api('POST', '/intentions', $ana, ['text' => 'Pela saúde da minha mãe, que fará uma cirurgia.', 'is_anonymous' => true])->assertCreated(), ['message']);
        $intention = PrayerIntention::query()->where('status', 'approved')->orderBy('id')->firstOrFail();
        $this->record('intencao_rezar', 'POST', '/intentions/{uuid}/pray', $this->api('POST', "/intentions/{$intention->uuid}/pray", $ana), ['prayers_count', 'prayed']);
        $this->record('intencoes_gratuito', 'GET', '/intentions', $this->api('GET', '/intentions', $ana), ['data' => ['*' => ['id', 'text', 'author', 'prayers_count', 'prayed']]]);
        $this->record('avisos_gratuito', 'GET', '/announcements', $this->api('GET', '/announcements', $ana), ['*' => self::ANNOUNCEMENT]);

        // Aparelho, senha e saída.
        $this->record('dispositivo_registrar', 'POST', '/me/devices', $this->api('POST', '/me/devices', $ana, ['fcm_token' => str_repeat('f', 152), 'platform' => 'android', 'app_version' => '3.0.0', 'notifications_enabled' => true]), ['message']);
        $this->record('dispositivo_remover', 'DELETE', '/me/devices', $this->api('DELETE', '/me/devices', $ana, ['fcm_token' => str_repeat('f', 152)]), ['message']);
        $this->record('me_senha', 'PUT', '/me/password', $this->api('PUT', '/me/password', $ana, ['current_password' => 'SenhaForte2026', 'password' => 'NovaSenha2027', 'password_confirmation' => 'NovaSenha2027']), ['message']);
        // Cada saída invalida o token usado: a próxima chamada entra de novo (novo token), como o app faria.
        $this->record('auth_sair', 'POST', '/auth/logout', $this->api('POST', '/auth/logout', $ana)->assertOk(), ['message']);
        unset($this->tokens[$ana->id]);
        $this->record('auth_sair_todos', 'POST', '/auth/logout-all', $this->api('POST', '/auth/logout-all', $ana)->assertOk(), ['message']);
        unset($this->tokens[$ana->id]);
        $this->record('me_excluir', 'DELETE', '/me', $this->api('DELETE', '/me', $ana, ['confirmation' => 'EXCLUIR', 'password' => 'NovaSenha2027'])->assertOk(), ['message']);
        $this->assertSoftDeleted($ana);
    }

    // ───────────────────────── Assinante ─────────────────────────

    public function test_subscriber_endpoints(): void
    {
        // Assinante pelo site (Pix aprovado no gateway simulado).
        $web = $this->makeUser(attributes: ['name' => 'Bruno Lima', 'email' => 'bruno@exemplo.com']);
        $checkout = app(CheckoutService::class);
        $payment = $checkout->start($web, Plan::query()->where('slug', 'anual')->firstOrFail(), PaymentMethod::Pix, null);
        $checkout->apply($payment, new GatewayPayment((string) $payment->provider_payment_id, PaymentStatus::Approved, $payment->amount_cents, $payment->uuid));

        $this->record('me_assinante', 'GET', '/me', $this->api('GET', '/me', $web), self::USER)
            ->assertJsonPath('is_premium', true)->assertJsonPath('subscription.status', 'active');
        $this->record('home_assinante', 'GET', '/home', $this->api('GET', '/home', $web), self::HOME)
            ->assertJsonPath('daily_verse.reflection_locked', false)->assertJsonPath('is_premium', true);
        $this->record('oracao_assinante', 'GET', '/prayers/{slug}', $this->api('GET', '/prayers/'.$this->premiumPrayer()->slug, $web), self::PRAYER_DETAIL)->assertJsonPath('locked', false);
        $this->record('jornada_assinante', 'GET', '/journeys/{slug}', $this->api('GET', '/journeys/evangelho-de-joao-em-21-dias', $web), self::JOURNEY_DETAIL)
            ->assertJsonPath('locked', false)->assertJsonPath('steps.1.locked', false);
        $this->record('liturgia_assinante', 'GET', '/liturgy?date={data}', $this->api('GET', '/liturgy?date=2026-10-04', $web), self::LITURGY)
            ->assertJsonPath('homily_locked', false);
        $verse = $this->api('GET', '/bible/books/joao/chapters/3')->json('verses.2.id');
        $this->record('biblia_explicar', 'POST', '/bible/explain', $this->api('POST', '/bible/explain', $web, ['verse_ids' => [$verse]]), ['reference', 'explanation', 'disclaimer']);
        $this->record('avisos_assinante', 'GET', '/announcements', $this->api('GET', '/announcements', $web), ['*' => self::ANNOUNCEMENT]);

        // Assinante pelo app: Google Play com teste grátis (validado no "Google", status trialing).
        $play = $this->makeUser(attributes: ['name' => 'Carla Dias', 'email' => 'carla@exemplo.com']);
        $this->playPurchase = [
            'subscriptionState' => 'SUBSCRIPTION_STATE_ACTIVE',
            'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_PENDING',
            'latestOrderId' => 'GPA.3312-5566-7788-99001',
            'startTime' => now()->toIso8601String(),
            'externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => GooglePlayBilling::obfuscatedAccountId($play)],
            'lineItems' => [[
                'productId' => 'sancta_plus',
                'expiryTime' => now()->addDays(7)->toIso8601String(),
                'autoRenewingPlan' => ['autoRenewEnabled' => true],
                'offerDetails' => ['basePlanId' => 'anual', 'offerId' => 'teste-gratis-7-dias', 'offerTags' => ['trial']],
            ]],
        ];
        $this->record('billing_verificar_play', 'POST', '/billing/google-play/verify', $this->api('POST', '/billing/google-play/verify', $play, [
            'product_id' => 'sancta_plus', 'purchase_token' => 'token-de-compra-do-google-play-0123456789',
        ])->assertOk(), ['subscription' => self::SUBSCRIPTION, 'user' => self::USER])
            ->assertJsonPath('subscription.status', 'trialing')->assertJsonPath('user.is_premium', true);
        $this->record('me_assinante_teste_gratis', 'GET', '/me', $this->api('GET', '/me', $play), self::USER)
            ->assertJsonPath('subscription.status', 'trialing')->assertJsonPath('subscription.provider', 'google_play')->assertJsonPath('subscription.auto_renew', true);
    }

    // ───────────────────────── Erros ─────────────────────────

    public function test_error_responses_the_app_understands(): void
    {
        $this->record('erro_401', 'GET', '/me', $this->api('GET', '/me')->assertUnauthorized(), ['message']);
        $this->record('erro_404', 'GET', '/prayers/{slug}', $this->api('GET', '/prayers/nao-existe')->assertNotFound(), ['message']);
        $this->record('erro_422', 'POST', '/auth/register', $this->api('POST', '/auth/register', null, ['name' => 'A', 'email' => 'invalido', 'password' => '123', 'device_name' => 'Android'])
            ->assertUnprocessable(), ['message', 'errors' => ['name', 'email', 'password', 'accept_terms']]);

        $suspended = $this->makeUser(attributes: ['email' => 'suspensa@exemplo.com']);
        $suspended->forceFill(['status' => 'suspended'])->save();
        $this->record('erro_403', 'POST', '/auth/login', $this->api('POST', '/auth/login', null, ['email' => 'suspensa@exemplo.com', 'password' => 'Senha123segura', 'device_name' => 'Android'])
            ->assertForbidden(), ['message', 'errors' => ['email']]);

        for ($i = 0; $i < 5; $i++) {
            $this->api('POST', '/auth/login', null, ['email' => 'alguem@exemplo.com', 'password' => 'errada-'.$i, 'device_name' => 'Android'])->assertUnprocessable();
        }
        $this->record('erro_429', 'POST', '/auth/login', $this->api('POST', '/auth/login', null, ['email' => 'alguem@exemplo.com', 'password' => 'errada', 'device_name' => 'Android'])
            ->assertStatus(429), ['message', 'errors' => ['email']]);
    }

    // ───────────────────────── Estruturas esperadas ─────────────────────────

    private const PRAYER = ['id', 'slug', 'title', 'subtitle', 'category' => ['slug', 'name', 'color'], 'cover_url', 'duration_minutes', 'has_audio', 'is_premium', 'is_featured', 'locked'];

    private const PRAYER_DETAIL = [...self::PRAYER, 'description', 'author', 'audio_url', 'blocks' => ['*' => ['type']], 'is_favorite'];

    private const JOURNEY = ['id', 'slug', 'title', 'type', 'type_label', 'subtitle', 'cover_url', 'color', 'is_premium', 'locked', 'steps_count', 'completed_steps'];

    private const JOURNEY_DETAIL = [...self::JOURNEY, 'description', 'steps' => ['*' => ['id', 'position', 'title', 'bible_reference', 'duration_minutes', 'completed', 'blocks', 'prayer_slug', 'audio_url', 'locked']]];

    private const CHURCH = ['id', 'slug', 'name', 'type', 'type_label', 'city', 'state', 'address', 'latitude', 'longitude', 'is_verified', 'cover_url', 'distance_km', 'next_mass'];

    private const CHURCH_DETAIL = [...self::CHURCH, 'description', 'diocese', 'contacts', 'maps_url', 'schedules', 'is_favorite'];

    private const CHAPTER = ['book' => ['name', 'abbreviation', 'slug', 'testament', 'chapters_count'], 'chapter', 'previous', 'next', 'verses' => ['*' => ['id', 'number', 'text', 'highlight', 'note', 'bookmarked']]];

    private const LITURGY = ['date', 'title', 'color', 'color_hex', 'readings' => ['*' => ['label', 'reference', 'text']], 'prayers', 'homily', 'homily_author', 'homily_locked'];

    private const PLAN = [
        'slug', 'name', 'description', 'interval', 'interval_count', 'period_label', 'price_cents', 'price_formatted', 'compare_at_price_cents', 'compare_at_price_formatted',
        'monthly_equivalent_cents', 'monthly_equivalent_formatted', 'savings_percent', 'trial_days', 'max_installments', 'badge', 'is_featured', 'features', 'google_play' => ['product_id', 'base_plan_id'],
    ];

    private const ANNOUNCEMENT = ['id', 'title', 'body', 'image_url', 'kind', 'category', 'cta_label', 'cta_url', 'frequency', 'dismissible', 'priority', 'updated_at'];

    private const SUBSCRIPTION = ['id', 'plan', 'plan_slug', 'status', 'status_label', 'provider', 'auto_renew', 'starts_at', 'ends_at', 'manage_url'];

    private const USER = [
        'id', 'name', 'first_name', 'email', 'email_verified', 'avatar_url', 'has_password', 'google_linked', 'theme', 'notification_preferences', 'marketing_opt_in',
        'is_premium', 'subscription', 'stats' => ['current_streak', 'longest_streak', 'total_prayer_minutes'], 'created_at',
    ];

    private const AUTH = ['token', 'token_type', 'expires_at', 'user' => self::USER];

    private const HOME = [
        'greeting' => ['title', 'subtitle'], 'streak',
        'daily_verse' => ['reference', 'text', 'image_url', 'reflection_title', 'reflection', 'reflection_locked'],
        'liturgy' => ['date', 'title', 'color', 'color_hex', 'gospel_reference'],
        'saint' => ['name', 'title', 'biography', 'image_url'],
        'continue_journey',
        'period_prayers' => ['morning' => self::PRAYER, 'afternoon' => self::PRAYER, 'night' => self::PRAYER],
        'featured_journeys' => ['*' => self::JOURNEY],
        'featured_prayers' => ['*' => self::PRAYER],
        'news' => ['*' => ['id', 'title', 'summary', 'image_url', 'source', 'url', 'published_at']],
        'live_channels' => ['*' => ['id', 'name', 'thumbnail_url', 'url', 'type', 'is_live']],
        'live_now' => ['*' => ['id', 'name', 'category', 'is_live', 'on_schedule_now', 'title', 'started_at', 'watch_url', 'thumbnail_url', 'logo_url', 'topic']],
        'events' => ['*' => ['slug', 'title', 'starts_at', 'location', 'cover_url', 'is_premium']],
        'is_premium',
    ];

    // ───────────────────────── Infraestrutura ─────────────────────────

    /** Chamada como o app faz: JSON, token Bearer do usuário (ou visitante) e sem estado entre requisições. */
    private function api(string $method, string $uri, ?User $as = null, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $headers = $as ? ['Authorization' => 'Bearer '.($this->tokens[$as->id] ??= $as->createToken('Android', ['app'], now()->addDays(60))->plainTextToken)] : [];

        return $this->json($method, '/api/v1'.$uri, $data, $headers + ['X-App-Version' => '3.0.0']);
    }

    /** Confere a estrutura e, com SANCTA_WRITE_FIXTURES=1, grava `{_contract, body}` em app/test/fixtures/api/<nome>.json. */
    private function record(string $name, string $method, string $route, TestResponse $response, array $structure): TestResponse
    {
        $this->assertLessThan(500, $response->getStatusCode(), "{$name}: ".$response->getContent());
        if ($structure !== []) {
            $response->assertJsonStructure($structure);
        }

        if (getenv('SANCTA_WRITE_FIXTURES') === '1') {
            $dir = dirname(base_path()).'/app/test/fixtures/api';
            File::ensureDirectoryExists($dir);
            $payload = [
                '_contract' => ['method' => $method, 'route' => $route, 'status' => $response->getStatusCode()],
                'body' => $this->redact(json_decode($response->getContent())),
            ];
            File::put("{$dir}/{$name}.json", json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n");
        }

        return $response;
    }

    /** Tokens de acesso viram um marcador (o formato importa, o valor não). */
    private function redact(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $key => $item) {
                $value->{$key} = $key === 'token' && is_string($item) ? '1|token-de-exemplo' : $this->redact($item);
            }
        } elseif (is_array($value)) {
            $value = array_map(fn ($item) => $this->redact($item), $value);
        }

        return $value;
    }

    /** O app lê estes campos como mapa: `{}` quando vazios — nunca `[]` (lista). */
    private function assertJsonObject(TestResponse $response, string $key): void
    {
        $decoded = json_decode($response->getContent());
        $this->assertTrue($decoded->{$key} === null || $decoded->{$key} instanceof stdClass, "'{$key}' deve ser objeto JSON: ".json_encode($decoded->{$key}));
    }

    private function premiumPrayer(): Prayer
    {
        return Prayer::query()->published()->where('is_premium', true)->orderBy('sort_order')->firstOrFail();
    }

    /** Seeders do projeto + o que eles não criam (liturgia sem rede, notícias, eventos, ao vivo, intenções…). */
    private function seedRealisticData(): void
    {
        $this->seed([PlanSeeder::class, BibleSeeder::class, PrayerSeeder::class, JourneySeeder::class, DailyVerseSeeder::class, SaintSeeder::class, ChurchSeeder::class, MediaSeeder::class, SiteContentSeeder::class]);

        // Liturgia (o serviço externo não é chamado: dias travados, como os editados no painel).
        LiturgyDay::query()->create([
            'date' => '2026-10-04', 'title' => '27º Domingo do Tempo Comum', 'color' => 'Verde', 'is_locked' => true, 'source' => 'manual', 'fetched_at' => now(),
            'readings' => [
                ['label' => 'Primeira leitura', 'reference' => 'Is 5,1-7', 'title' => 'A vinha do Senhor', 'refrain' => null, 'text' => 'Vou cantar para o meu amado o cântico do amigo para a sua vinha.'],
                ['label' => 'Salmo responsorial', 'reference' => 'Sl 79', 'title' => null, 'refrain' => 'A vinha do Senhor é a casa de Israel.', 'text' => 'Arrancastes do Egito esta videira.'],
                ['label' => 'Segunda leitura', 'reference' => 'Fl 4,6-9', 'title' => 'O Deus da paz', 'refrain' => null, 'text' => 'Não vos inquieteis com coisa alguma.'],
                ['label' => 'Evangelho', 'reference' => 'Mt 21,33-43', 'title' => 'A parábola dos vinhateiros', 'refrain' => null, 'text' => 'Naquele tempo, Jesus disse aos sumos sacerdotes e aos anciãos do povo.'],
            ],
            'prayers' => ['collect' => 'Ó Deus eterno e todo-poderoso, que nos concedeis mais do que merecemos e pedimos.', 'offerings' => 'Acolhei, Senhor, o sacrifício.', 'communion' => 'Saciados pelo pão do céu.'],
            'homily' => 'A vinha é cuidada com amor: Deus espera os frutos de justiça na nossa vida.', 'homily_author' => 'Pe. Marcos',
        ]);
        LiturgyDay::query()->create([
            'date' => '2026-10-05', 'title' => 'Segunda-feira da 27ª Semana do Tempo Comum', 'color' => 'Verde', 'is_locked' => true, 'source' => 'manual', 'fetched_at' => now(),
            'readings' => [['label' => 'Evangelho', 'reference' => 'Lc 10,25-37', 'title' => null, 'refrain' => null, 'text' => 'Um mestre da Lei se levantou e, querendo pôr Jesus em dificuldade, perguntou.']],
            'prayers' => [],
        ]);

        // Notícias (normalmente importadas por RSS).
        $source = NewsSource::query()->where('name', 'Vatican News')->firstOrFail();
        NewsArticle::query()->create(['news_source_id' => $source->id, 'title' => 'Papa convoca jornada de oração pela paz', 'summary' => 'Encontro reunirá fiéis do mundo inteiro.', 'url' => 'https://www.vaticannews.va/pt/papa/news/2026-10/jornada-oracao-paz.html', 'published_at' => now()->subHours(3), 'status' => 'published']);
        NewsArticle::query()->create(['title' => 'Santuário prepara a festa da Padroeira', 'summary' => 'Programação vai de 3 a 12 de outubro.', 'content' => '<h2>Programação</h2><p>Missas, novena e procissão.</p>', 'author' => 'Equipe Sancta', 'published_at' => now()->subDay(), 'status' => 'published', 'is_featured' => true]);

        Event::query()->create(['title' => 'Retiro online de outubro', 'description' => '<p>Três noites de oração guiada.</p>', 'location' => 'Online', 'link' => 'https://sancta.app.br/retiro', 'starts_at' => now()->addDays(3)->setTime(20, 0), 'ends_at' => now()->addDays(3)->setTime(21, 30), 'is_online' => true, 'is_premium' => true, 'status' => 'published']);

        // Ao vivo: um canal detectado ao vivo e um no horário da agenda.
        $live = LiveChannel::query()->where('name', 'TV Aparecida')->firstOrFail();
        $live->forceFill(['category' => 'missa', 'live_status' => 'live', 'live_video_id' => 'LIVEvideo01', 'live_title' => 'Santa Missa do Santuário', 'live_started_at' => now()->subMinutes(30), 'live_checked_at' => now()->subMinute()])->save();
        $live->schedules()->create(['weekday' => 0, 'starts_at' => '08:00', 'duration_minutes' => 90, 'title' => 'Missa de domingo']);
        LiveChannel::query()->where('name', 'TV Canção Nova')->firstOrFail()->schedules()->create(['weekday' => null, 'starts_at' => '18:00', 'duration_minutes' => 60, 'title' => 'Terço da Misericórdia']);

        // Igreja sem horários nem contatos (campos-mapa vazios).
        Church::query()->create(['name' => 'Capela São José', 'type' => 'capela', 'city' => 'Porto Seguro', 'state' => 'BA', 'status' => 'published']);

        // Intenções aprovadas.
        $author = $this->makeUser(attributes: ['name' => 'Maria das Graças', 'email' => 'maria@exemplo.com']);
        foreach ([['Pela paz nas famílias da nossa comunidade.', false], ['Por um emprego para o meu filho.', true]] as [$text, $anonymous]) {
            $intention = new PrayerIntention(['text' => $text, 'is_anonymous' => $anonymous]);
            $intention->forceFill(['user_id' => $author->id, 'status' => 'approved', 'moderated_at' => now()->subHour(), 'prayers_count' => 3])->save();
        }

        Testimonial::query()->create(['author' => 'Maria', 'location' => 'Salvador', 'content' => 'Rezo o terço todos os dias com o app.', 'rating' => 5, 'is_active' => true]);
        Announcement::query()->create(['title' => 'Novena de Aparecida', 'body' => 'Reze conosco até o dia 12.', 'kind' => 'banner', 'category' => 'event', 'cta_label' => 'Rezar a novena', 'cta_url' => 'sancta://jornada/novena-nossa-senhora-aparecida', 'audience' => 'premium', 'platform' => 'both', 'frequency' => 'daily', 'priority' => 5, 'dismissible' => true, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10)]);
    }
}
