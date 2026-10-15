<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\Google\ServiceAccountToken;
use App\Services\Integrations;
use App\Services\IntegrationTester;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;
use UnitEnum;

/**
 * Credenciais e segurança: Mercado Pago (Pix/cartão), Google Play, login com Google,
 * Firebase, Gemini, YouTube (lives), e-mail (SMTP) e acesso ao painel — tudo editável aqui.
 *
 * Segurança: só super administrador; segredos criptografados e nunca exibidos;
 * salvar exige a senha atual (com limite de tentativas); a auditoria registra
 * QUAIS campos mudaram, nunca os valores; a lista de IPs não aceita trancar você fora.
 */
class IntegrationSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Integrações e segurança';

    protected static ?string $title = 'Integrações e segurança';

    protected static ?string $slug = 'integracoes';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRoleAtLeast(UserRole::SuperAdmin);
    }

    public function getSubheading(): ?string
    {
        return 'O que for salvo aqui vale na hora, sem mexer no servidor. Senhas e chaves ficam criptografadas e nunca são exibidas de novo — deixe em branco para manter a atual.';
    }

    public function mount(Integrations $integrations): void
    {
        $this->form->fill($integrations->formState());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make()->persistTabInQueryString()->tabs([
                $this->paymentsTab(),
                $this->googleTab(),
                $this->aiTab(),
                $this->contentTab(),
                $this->mailTab(),
                $this->securityTab(),
            ]),
            Section::make('Confirmação')->compact()->schema([
                TextInput::make('confirm_password')
                    ->label('Sua senha atual')
                    ->helperText('Obrigatória para salvar alterações nesta página.')
                    ->password()
                    ->autocomplete('current-password')
                    ->required()
                    ->maxWidth('md'),
            ]),
        ]);
    }

    private function paymentsTab(): Tab
    {
        return Tab::make('Pagamentos')->icon(Heroicon::OutlinedBanknotes)->schema([
            Section::make('Mercado Pago — Pix e cartão no site')
                ->description(fn () => $this->status('mercadopago.access_token'))
                ->headerActions([$this->testAction('test_mercadopago', fn (IntegrationTester $t) => $t->mercadoPago())])
                ->columns(2)
                ->schema([
                    Toggle::make('payments.pix_enabled')->label('Aceitar Pix'),
                    Toggle::make('payments.card_enabled')->label('Aceitar cartão de crédito'),
                    $this->secretInput('mercadopago.access_token', 'Access Token')
                        ->rule('starts_with:APP_USR-,TEST-')
                        ->validationMessages(['starts_with' => 'O Access Token começa com APP_USR- (produção) ou TEST- (teste).'])
                        ->helperText('Mercado Pago → Suas integrações → sua aplicação → Credenciais. Use as de teste (TEST-…) para testar sem cobrar.'),
                    TextInput::make('mercadopago.public_key')
                        ->label('Public Key')
                        ->rule('starts_with:APP_USR-,TEST-')
                        ->validationMessages(['starts_with' => 'A Public Key começa com APP_USR- ou TEST-.'])
                        ->maxLength(255),
                    $this->secretInput('mercadopago.webhook_secret', 'Assinatura secreta do webhook')
                        ->helperText('Mercado Pago → Suas integrações → Webhooks → "Assinatura secreta". Sem ela, as notificações de pagamento são recusadas.'),
                    TextInput::make('payments.pix_expiration_minutes')
                        ->label('Validade do QR Code Pix (minutos)')
                        ->integer()->minValue(5)->maxValue(1440)->required(),
                    Placeholder::make('mercadopago_webhook_url')
                        ->label('URL de notificações (cadastre no Mercado Pago, evento "Pagamentos")')
                        ->content(route('webhooks.mercadopago'))
                        ->columnSpanFull(),
                ]),
            Section::make('Google Play — assinaturas compradas no app')
                ->description(fn () => $this->status('google_play.service_account'))
                ->headerActions([$this->testAction('test_google_play', fn (IntegrationTester $t) => $t->googlePlay())])
                ->columns(2)
                ->schema([
                    TextInput::make('google_play.package_name')
                        ->label('Nome do pacote do app')
                        ->regex('/^[a-zA-Z][\w]*(\.[a-zA-Z][\w]*)+$/')
                        ->required(),
                    $this->serviceAccountUpload('google_play.service_account', 'Conta de serviço (arquivo .json)')
                        ->helperText(fn () => $this->secretStatus('google_play.service_account').' Play Console → Usuários e permissões → convide a conta de serviço com acesso a "Pedidos e assinaturas".'),
                    TextInput::make('google_play.rtdn_audience')
                        ->label('Audiência do Pub/Sub (notificações em tempo real)')
                        ->helperText('O valor "audience" configurado na assinatura push do Pub/Sub (normalmente a URL abaixo).')
                        ->maxLength(255),
                    TextInput::make('google_play.rtdn_service_account_email')
                        ->label('E-mail da conta que envia as notificações')
                        ->email()->maxLength(191),
                    Placeholder::make('google_play_webhook_url')
                        ->label('URL de notificações em tempo real (endpoint push do Pub/Sub)')
                        ->content(route('webhooks.google-play'))
                        ->columnSpanFull(),
                    Placeholder::make('plans_hint')
                        ->hiddenLabel()
                        ->content('Os IDs das assinaturas (ex.: sancta_plus) e dos planos-base ficam em Assinaturas → Planos; o app lê de lá automaticamente.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private function googleTab(): Tab
    {
        return Tab::make('Login e notificações')->icon(Heroicon::OutlinedBell)->schema([
            Section::make('Entrar com Google')
                ->description(fn () => filled(config('services.google.web_client_id')) || config('services.google.client_ids') !== []
                    ? '✅ Configurado' : '⚠️ Não configurado — o botão "Continuar com o Google" não funciona.')
                ->columns(2)
                ->schema([
                    TextInput::make('google.web_client_id')
                        ->label('Client ID Web (usado pelo app)')
                        ->helperText('Google Cloud → APIs e serviços → Credenciais → ID do cliente OAuth do tipo "Aplicativo da Web". O app recebe este valor automaticamente.')
                        ->rule('ends_with:.apps.googleusercontent.com')
                        ->maxLength(191),
                    TagsInput::make('google.client_ids')
                        ->label('Outros client IDs aceitos (opcional)')
                        ->helperText('Ex.: o client ID Android. Digite e tecle Enter.')
                        ->placeholder('…apps.googleusercontent.com'),
                ]),
            Section::make('Notificações push (Firebase)')
                ->description(fn () => $this->status('fcm.service_account'))
                ->headerActions([$this->testAction('test_firebase', fn (IntegrationTester $t) => $t->firebase())])
                ->columns(2)
                ->schema([
                    TextInput::make('fcm.project_id')
                        ->label('ID do projeto Firebase')
                        ->regex('/^[a-z0-9-]+$/')
                        ->maxLength(100),
                    $this->serviceAccountUpload('fcm.service_account', 'Conta de serviço do Firebase (arquivo .json)')
                        ->helperText(fn () => $this->secretStatus('fcm.service_account').' Firebase → Configurações do projeto → Contas de serviço → Gerar nova chave privada.'),
                ]),
        ]);
    }

    private function aiTab(): Tab
    {
        return Tab::make('Inteligência artificial')->icon(Heroicon::OutlinedSparkles)->schema([
            Section::make('Google Gemini — explicação de versículos (Sancta+)')
                ->description(fn () => $this->status('gemini.api_key'))
                ->headerActions([$this->testAction('test_gemini', fn (IntegrationTester $t) => $t->gemini())])
                ->columns(3)
                ->schema([
                    $this->secretInput('gemini.api_key', 'Chave da API')
                        ->helperText('Google AI Studio → Get API key. A chave fica só no servidor; o app nunca a recebe.')
                        ->columnSpan(3),
                    TextInput::make('gemini.model')->label('Modelo')->regex('/^[a-z0-9.\-]+$/i')->required()->maxLength(60),
                    TextInput::make('ai.daily_limit')->label('Limite diário por assinante')->integer()->minValue(1)->maxValue(1000)->required(),
                ]),
        ]);
    }

    private function contentTab(): Tab
    {
        return Tab::make('Conteúdo')->icon(Heroicon::OutlinedVideoCamera)->schema([
            Section::make('YouTube — transmissões ao vivo')
                ->description(fn () => app(Integrations::class)->source('youtube.api_key') === null
                    ? '⚠️ Não configurado — o status "ao vivo" segue a agenda cadastrada em cada canal.'
                    : $this->status('youtube.api_key').' — lives detectadas automaticamente a cada 3 minutos.')
                ->headerActions([$this->testAction('test_youtube', fn (IntegrationTester $t) => $t->youtube())])
                ->schema([
                    $this->secretInput('youtube.api_key', 'Chave da YouTube Data API v3')
                        ->helperText('Google Cloud Console → APIs e serviços → Biblioteca → ative a "YouTube Data API v3" → Credenciais → Criar credenciais → Chave de API. Restrinja a chave à YouTube Data API v3. A chave fica só no servidor; o app nunca a recebe.'),
                    Placeholder::make('youtube_quota_hint')
                        ->label('Como funciona a detecção')
                        ->hiddenLabel()
                        ->content('Como funciona: a cada 3 minutos o servidor lê o RSS público de cada canal (Mídia → Ao vivo, campo "ID do canal") e consulta até 50 vídeos por chamada — 1 unidade de cota por chamada. Com poucos canais, o consumo fica bem abaixo da cota gratuita de 10.000 unidades por dia. Quando uma live começa, quem ativou "Me avise" no app recebe uma notificação.'),
                ]),
        ]);
    }

    private function mailTab(): Tab
    {
        $smtp = fn (Get $get): bool => $get('mail.mailer') === 'smtp';

        // Se o servidor usa outro serviço (ex.: ses, resend), ele aparece como opção para não travar o formulário.
        $options = ['smtp' => 'SMTP (recomendado)', 'log' => 'Desativado — apenas registrar (desenvolvimento)'];
        $serverMailer = (string) app(Integrations::class)->defaultValue('mail.mailer');
        if ($serverMailer !== '' && ! array_key_exists($serverMailer, $options)) {
            $options[$serverMailer] = "Definido no servidor ({$serverMailer})";
        }

        return Tab::make('E-mail')->icon(Heroicon::OutlinedEnvelope)->schema([
            Section::make('Envio de e-mails (confirmação de cadastro, senha, recibos)')
                ->description(fn () => match (config('mail.default')) {
                    'smtp' => '✅ Enviando por SMTP',
                    'log' => '⚠️ Desativado — os e-mails só ficam registrados no log do servidor.',
                    default => '✅ Enviando por '.config('mail.default').' (definido no servidor)',
                })
                ->headerActions([$this->testAction('test_mail', fn (IntegrationTester $t) => $t->mail(auth()->user()), 'Enviar e-mail de teste para mim')])
                ->columns(2)
                ->schema([
                    Select::make('mail.mailer')
                        ->label('Forma de envio')
                        ->options($options)
                        ->selectablePlaceholder(false)
                        ->live()
                        ->required(),
                    Select::make('mail.scheme')
                        ->label('Segurança')
                        ->options(['smtp' => 'STARTTLS (porta 587)', 'smtps' => 'SSL/TLS (porta 465)'])
                        ->placeholder('Automática')
                        ->visible($smtp),
                    TextInput::make('mail.host')->label('Servidor SMTP')->placeholder('smtp.seuprovedor.com')->maxLength(191)->visible($smtp)->required($smtp),
                    TextInput::make('mail.port')->label('Porta')->integer()->minValue(1)->maxValue(65535)->visible($smtp)->required($smtp),
                    TextInput::make('mail.username')->label('Usuário')->maxLength(191)->autocomplete('off')->visible($smtp),
                    $this->secretInput('mail.password', 'Senha do SMTP')->visible($smtp),
                    TextInput::make('mail.from_address')->label('E-mail do remetente')->email()->required()->maxLength(191),
                    TextInput::make('mail.from_name')->label('Nome do remetente')->required()->maxLength(100),
                ]),
        ]);
    }

    private function securityTab(): Tab
    {
        $ip = request()->ip();

        return Tab::make('Segurança do painel')->icon(Heroicon::OutlinedLockClosed)->schema([
            Section::make('Acesso ao painel')->columns(2)->schema([
                TagsInput::make('admin.allowed_ips')
                    ->label('Permitir o painel só destes IPs (opcional)')
                    ->helperText("Vazio = qualquer IP (com login). Aceita faixas como 200.10.0.0/24. Seu IP agora: {$ip} — ele precisa estar na lista.")
                    ->placeholder('Digite um IP e tecle Enter'),
                Toggle::make('admin.require_mfa')
                    ->label('Exigir verificação em duas etapas (2FA) para toda a equipe')
                    ->helperText(fn () => (bool) app(Integrations::class)->defaultValue('admin.require_mfa')
                        ? 'Exigido também pelo servidor (.env) — não pode ser desligado aqui.'
                        : 'Quem ainda não ativou será levado a configurar no próximo acesso (app autenticador).'),
            ]),
            Section::make('Definido no servidor')
                ->description('Por segurança, estes itens não podem ser alterados pelo painel: uma conta comprometida não consegue desligá-los.')
                ->columns(2)
                ->schema([
                    Placeholder::make('debug')->label('Modo debug')
                        ->content(config('app.debug') ? '⚠️ Ligado — desligue no servidor (APP_DEBUG=false) antes de publicar' : '✅ Desligado'),
                    Placeholder::make('https')->label('Cookies seguros (HTTPS)')
                        ->content(config('session.secure') ? '✅ Ativados' : 'Desativados neste ambiente local — ativam sozinhos em produção (HTTPS)'),
                    Placeholder::make('admin_path')->label('Endereço do painel')->content(url(config('sancta.admin.path'))),
                    Placeholder::make('fake')->label('Pagamentos simulados')
                        ->content(app(PaymentGatewayManager::class)->fakeAllowed() ? '🧪 Ativos (somente neste ambiente local)' : '✅ Desativados'),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getFormContentComponent()]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([Action::make('save')->label('Salvar integrações')->submit('save')->keyBindings(['mod+s'])]),
            ]);
    }

    public function save(Integrations $integrations): void
    {
        $state = $this->form->getState();
        $this->confirmPassword((string) ($state['confirm_password'] ?? ''));

        $values = [];
        foreach (array_keys(Integrations::FIELDS) as $field) {
            if (! Arr::has($state, $field)) {
                continue; // campo oculto (ex.: SMTP com envio desativado): mantém o valor salvo
            }
            $values[$field] = str_ends_with($field, 'service_account')
                ? $this->readServiceAccount($field, data_get($state, $field))
                : data_get($state, $field);
        }

        $this->validateLists($values);

        $changed = $integrations->save($values);
        if ($changed !== []) {
            AuditLog::record('integrations.updated', null, [], ['campos' => $changed]);
        }

        $this->form->fill($integrations->formState());

        Notification::make()
            ->title($changed === [] ? 'Nada foi alterado' : 'Integrações salvas')
            ->body($changed === [] ? null : 'As alterações já estão valendo.')
            ->success()
            ->send();
    }

    /** Campo de segredo: em branco mantém o valor; "Remover" volta ao padrão do servidor. */
    private function secretInput(string $field, string $label): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->password()
            ->autocomplete('new-password')
            ->maxLength(2048)
            ->placeholder(fn () => $this->secretStatus($field))
            ->hintAction($this->forgetAction($field));
    }

    private function serviceAccountUpload(string $field, string $label): FileUpload
    {
        return FileUpload::make($field)
            ->label($label)
            ->acceptedFileTypes(['application/json', 'text/plain'])
            ->maxSize(64)
            ->storeFiles(false)
            ->hintAction($this->forgetAction($field));
    }

    private function forgetAction(string $field): Action
    {
        return Action::make('forget_'.str_replace('.', '_', $field))
            ->label('Remover')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Remover a credencial salva no painel?')
            ->modalDescription('O sistema volta a usar o valor do servidor (.env), se houver.')
            ->visible(fn (): bool => app(Integrations::class)->inPanel($field))
            ->action(function () use ($field): void {
                app(Integrations::class)->forget($field);
                AuditLog::record('integrations.removed', null, [], ['campos' => [$field]]);
                Notification::make()->title('Credencial removida')->success()->send();
            });
    }

    /** @param Closure(IntegrationTester): string $test */
    private function testAction(string $name, Closure $test, string $label = 'Testar'): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->tooltip('Usa os valores já salvos')
            ->action(function (IntegrationTester $tester) use ($test): void {
                $key = 'integrations-test:'.auth()->id();
                if (RateLimiter::tooManyAttempts($key, 10)) {
                    Notification::make()->title('Muitos testes seguidos')->body('Aguarde um minuto.')->warning()->send();

                    return;
                }
                RateLimiter::hit($key, 60);

                try {
                    Notification::make()->title('Funcionou!')->body($test($tester))->success()->send();
                } catch (RuntimeException $e) {
                    Notification::make()->title('Não funcionou')->body($e->getMessage())->danger()->persistent()->send();
                }
            });
    }

    private function status(string $field): string
    {
        return match (app(Integrations::class)->source($field)) {
            'panel' => '✅ Configurado no painel',
            'env' => '✅ Configurado no servidor (.env)',
            default => '⚠️ Não configurado',
        };
    }

    private function secretStatus(string $field): string
    {
        $integrations = app(Integrations::class);
        $hint = $integrations->secretHint($field);

        return match ($integrations->source($field)) {
            'panel' => "Salvo no painel ({$hint}). Deixe em branco para manter.",
            'env' => "Usando o valor do servidor ({$hint}). Preencha para substituir.",
            default => 'Não configurado.',
        };
    }

    private function confirmPassword(string $password): void
    {
        $user = auth()->user();
        $key = 'integrations-save:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['data.confirm_password' => 'Muitas tentativas. Aguarde alguns minutos.']);
        }

        if (! Hash::check($password, (string) $user->getAuthPassword())) {
            RateLimiter::hit($key, 600);
            throw ValidationException::withMessages(['data.confirm_password' => 'Senha incorreta.']);
        }

        RateLimiter::clear($key);
    }

    private function readServiceAccount(string $field, mixed $upload): ?string
    {
        $file = is_array($upload) ? Arr::first($upload) : $upload;
        if (! $file instanceof TemporaryUploadedFile) {
            return null; // nada enviado: mantém a credencial atual
        }

        $json = (string) $file->get();
        $file->delete();

        if (ServiceAccountToken::parse($json) === null) {
            throw ValidationException::withMessages(["data.{$field}" => 'Arquivo inválido: envie o JSON da conta de serviço (contém "client_email" e "private_key").']);
        }

        return $json;
    }

    /** Valida listas e impede que a restrição de IP tranque quem está salvando. */
    private function validateLists(array $values): void
    {
        $ips = array_values(array_filter(array_map('trim', (array) ($values['admin.allowed_ips'] ?? [])), 'filled'));
        foreach ($ips as $entry) {
            if (! Integrations::isValidIpEntry($entry)) {
                throw ValidationException::withMessages(['data.admin.allowed_ips' => "\"{$entry}\" não é um IP ou faixa válida."]);
            }
        }
        if ($ips !== [] && ! IpUtils::checkIp((string) request()->ip(), $ips)) {
            throw ValidationException::withMessages(['data.admin.allowed_ips' => 'Seu IP atual ('.request()->ip().') não está na lista — você perderia o acesso ao painel.']);
        }

        foreach ((array) ($values['google.client_ids'] ?? []) as $id) {
            if (! str_ends_with(trim((string) $id), '.apps.googleusercontent.com')) {
                throw ValidationException::withMessages(['data.google.client_ids' => "\"{$id}\" não parece um client ID do Google (termina em .apps.googleusercontent.com)."]);
            }
        }
    }
}
