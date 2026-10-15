<?php

namespace App\Providers;

use App\Models;
use App\Models\User;
use App\Policies\AdminPolicy;
use App\Policies\ContentPolicy;
use App\Policies\GiftCodePolicy;
use App\Policies\ModerationPolicy;
use App\Policies\ReadOnlyAdminPolicy;
use App\Policies\UserPolicy;
use App\Rules\NoActiveContentUpload;
use App\Services\Integrations;
use App\Services\Settings;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(Integrations::class);
    }

    public function boot(): void
    {
        // Credenciais e segurança definidas no painel (Integrações e segurança) têm prioridade sobre o .env.
        $integrations = $this->app->make(Integrations::class);
        $integrations->apply();
        // A fila roda num processo longo: relê o painel antes de cada job (ex.: credencial do Firebase trocada).
        Queue::before(fn () => $integrations->refresh());

        // Fora de produção, qualquer tentativa de atribuição em massa de campo não permitido
        // (ex.: "role" vindo de um formulário) gera erro em vez de ser descartada em silêncio.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Respostas da API sem o envelope "data" para recursos individuais (listas paginadas mantêm data/meta).
        JsonResource::withoutWrapping();

        if ($this->app->isProduction()) {
            URL::forceHttps();
            // Em produção o cookie de sessão só trafega por HTTPS, independentemente do .env.
            config(['session.secure' => true]);
        }

        // Política de senha: mínimo 8, letras e números; em produção também verifica
        // se a senha já apareceu em vazamentos públicos (Have I Been Pwned, k-anonymity).
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        // Link de redefinição de senha aponta para o SITE (funciona para app e web).
        // Montado a partir do APP_URL (nunca do cabeçalho Host da requisição): evita que um Host forjado
        // faça o token de redefinição ser enviado para o domínio de outra pessoa.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => rtrim((string) config('app.url'), '/')
            .route('password.reset', ['token' => $token, 'email' => $user->email], false));

        // Uploads do painel vão para o disco público (mesmo domínio): nunca aceitar SVG/HTML/JS,
        // mesmo em campos ->image() (que aceitam "image/*").
        FileUpload::configureUsing(fn (FileUpload $upload) => $upload->rule(new NoActiveContentUpload));

        // Colunas editáveis das listagens (ex.: chave "Ativo") gravam direto pelo Livewire e o Filament
        // não consulta a policy: sem permissão de "update" no registro, a coluna fica desabilitada
        // (e o Filament recusa a gravação). Pentest fase 3 — SEC-10.
        foreach ([ToggleColumn::class, CheckboxColumn::class, TextInputColumn::class, SelectColumn::class] as $editable) {
            $editable::configureUsing(fn (Column $column) => $column->disabled(
                fn (?Model $record): bool => ! ($record && auth()->user()?->can('update', $record)),
            ));
        }

        $this->registerPolicies();
        $this->configureRateLimiting();
    }

    /**
     * Autorização do painel por papel (hierarquia: moderador < editor < admin < super admin).
     * Sem política registrada o Filament liberaria tudo — por isso TODO model do painel está mapeado.
     */
    private function registerPolicies(): void
    {
        $map = [
            ContentPolicy::class => [
                Models\Category::class, Models\Prayer::class, Models\Journey::class, Models\JourneyStep::class,
                Models\DailyVerse::class, Models\LiturgyDay::class, Models\Saint::class, Models\Event::class,
                Models\NewsSource::class, Models\NewsArticle::class, Models\LiveChannel::class, Models\Announcement::class,
                Models\Page::class, Models\Faq::class, Models\Testimonial::class, Models\Church::class, Models\ChurchSchedule::class,
                Models\LiveSchedule::class,
            ],
            ModerationPolicy::class => [Models\ChurchSubmission::class, Models\PrayerIntention::class],
            AdminPolicy::class => [Models\Plan::class, Models\Coupon::class, Models\Subscription::class, Models\PushCampaign::class, Models\Setting::class],
            ReadOnlyAdminPolicy::class => [Models\Payment::class, Models\WebhookEvent::class, Models\AuditLog::class, Models\Device::class],
            // Presentes: somente leitura + ação "Revogar" (admin+).
            GiftCodePolicy::class => [Models\GiftCode::class],
            UserPolicy::class => [User::class],
        ];

        foreach ($map as $policy => $models) {
            foreach ($models as $model) {
                Gate::policy($model, $policy);
            }
        }
    }

    private function configureRateLimiting(): void
    {
        $by = fn (Request $request) => $request->user()?->id ? 'u:'.$request->user()->id : 'ip:'.$request->ip();

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($by($r)));
        RateLimiter::for('search', fn (Request $r) => Limit::perMinute(30)->by($by($r)));
        RateLimiter::for('interactions', fn (Request $r) => Limit::perMinute(60)->by($by($r)));
        RateLimiter::for('auth', fn (Request $r) => [
            Limit::perMinute(10)->by('auth-ip:'.$r->ip()),
            Limit::perHour(60)->by('auth-ip-h:'.$r->ip()),
        ]);
        RateLimiter::for('password-reset', fn (Request $r) => [
            Limit::perMinute(3)->by('pr-ip:'.$r->ip()),
            Limit::perHour(10)->by('pr-email:'.sha1((string) $r->input('email'))),
        ]);
        RateLimiter::for('submissions', fn (Request $r) => Limit::perHour(10)->by($by($r)));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perHour(20)->by($by($r)));
        RateLimiter::for('ai', fn (Request $r) => Limit::perMinute(10)->by($by($r)));
        RateLimiter::for('checkout', fn (Request $r) => Limit::perMinute(10)->by($by($r)));
        RateLimiter::for('webhooks', fn (Request $r) => Limit::perMinute(300)->by('wh:'.$r->ip()));
        RateLimiter::for('web-forms', fn (Request $r) => Limit::perMinute(6)->by('wf:'.$r->ip()));
        // Resgate de presente (anti força bruta): por usuário e por IP, por minuto e por dia.
        RateLimiter::for('gift-redeem', fn (Request $r) => [
            Limit::perMinute(5)->by('gr-u:'.$r->user()?->id),
            Limit::perDay(20)->by('gr-u-d:'.$r->user()?->id),
            Limit::perMinute(10)->by('gr-ip:'.$r->ip()),
            Limit::perDay(50)->by('gr-ip-d:'.$r->ip()),
        ]);
        // "Gerar novo código" do presente (envia e-mail): poucas vezes por hora.
        RateLimiter::for('gift-regenerate', fn (Request $r) => Limit::perHour(3)->by('gg-u:'.$r->user()?->id));
    }
}
