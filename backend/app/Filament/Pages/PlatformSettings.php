<?php

namespace App\Filament\Pages;

use App\Enums\ThemePreference;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Prayer;
use App\Services\Content\PeriodPrayers;
use App\Services\PremiumContent;
use App\Services\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Configurações gerais da plataforma (sem publicar nova versão do app):
 * identidade, versão mínima/forçar atualização, manutenção, recursos liga/desliga
 * e textos do Sancta+. Credenciais e segurança: página IntegrationSettings.
 */
class PlatformSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Configurações';

    protected static ?string $title = 'Configurações da plataforma';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRoleAtLeast(UserRole::Admin);
    }

    public function mount(Settings $settings): void
    {
        $state = [];
        foreach (array_keys(Settings::DEFAULTS) as $key) {
            data_set($state, $key, $settings->get($key));
        }

        // Benefícios antigos (lista de strings) viram itens estruturados no formulário.
        data_set($state, 'premium.benefits', PremiumContent::normalizeBenefits($settings->get('premium.benefits')));

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make()->persistTabInQueryString()->tabs([
                Tab::make('Identidade')->icon(Heroicon::OutlinedSwatch)->columns(2)->schema([
                    TextInput::make('app.name')->label('Nome do app')->required()->maxLength(40),
                    TextInput::make('app.tagline')->label('Tagline')->maxLength(60),
                    TextInput::make('app.slogan')->label('Slogan')->maxLength(120)->columnSpanFull(),
                    TextInput::make('app.support_email')->label('E-mail de suporte')->email()->required(),
                    TextInput::make('app.support_whatsapp')->label('WhatsApp de suporte')->tel()->placeholder('5573999999999'),
                    TextInput::make('app.play_store_url')->label('Link da Play Store')->url()->rule('starts_with:https://'),
                    TextInput::make('app.instagram')->label('Instagram')->maxLength(80),
                    TextInput::make('app.youtube')->label('YouTube (URL)')->url()->rule('starts_with:https://'),
                ]),
                Tab::make('Aplicativo')->icon(Heroicon::OutlinedDevicePhoneMobile)->columns(2)->schema([
                    TextInput::make('app.min_version')->label('Versão mínima')->helperText('Versões abaixo desta são obrigadas a atualizar.')->regex('/^\d+\.\d+\.\d+$/')->required(),
                    TextInput::make('app.latest_version')->label('Versão mais recente')->helperText('Versões abaixo recebem um aviso opcional.')->regex('/^\d+\.\d+\.\d+$/')->required(),
                    Textarea::make('app.update_message')->label('Mensagem de atualização')->rows(2)->columnSpanFull(),
                    Toggle::make('app.maintenance')->label('Modo manutenção no app')->helperText('Exibe uma tela de manutenção para todos os usuários.'),
                    Textarea::make('app.maintenance_message')->label('Mensagem de manutenção')->rows(2),
                    Select::make('theme.default')->label('Tema padrão')->options(ThemePreference::class)->required(),
                    // Oração do período: cartão principal da tela Hoje e widget "Oração do dia".
                    Fieldset::make('Oração do período (tela Hoje e widget)')->columns(3)->columnSpanFull()->schema(
                        collect(['home.prayer_morning' => 'Manhã (5h–11h59)', 'home.prayer_afternoon' => 'Tarde (12h–17h59)', 'home.prayer_night' => 'Noite (18h–4h59)'])
                            ->map(fn (string $label, string $key) => Select::make($key)->label($label)
                                ->options(fn () => Prayer::query()->published()->orderBy('title')->pluck('title', 'id')->all())
                                ->searchable()
                                ->placeholder('Automático')
                                ->helperText('Vazio = escolha automática pelo título/categoria.'))
                            ->values()->all()
                    ),
                ]),
                Tab::make('Recursos')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->columns(3)->schema([
                    Toggle::make('features.ai_explanations')->label('Explicação de versículos com IA'),
                    Toggle::make('features.live')->label('Área "Ao vivo"'),
                    Toggle::make('features.news')->label('Notícias'),
                    Toggle::make('features.churches')->label('Mapa de igrejas'),
                    Toggle::make('features.intentions')->label('Intenções da comunidade'),
                    Toggle::make('features.events')->label('Eventos'),
                    Toggle::make('features.guest_mode')->label('Usar o app sem conta (visitante)'),
                    Toggle::make('features.web_bible')->label('Bíblia no site'),
                    Toggle::make('news.open_in_app')->label('Abrir notícias dentro do app'),
                ]),
                Tab::make('Sancta+')->icon(Heroicon::OutlinedStar)->columns(2)->schema([
                    TextInput::make('premium.name')->label('Nome da assinatura')->required()->maxLength(30),
                    TextInput::make('premium.headline')->label('Chamada')->required()->maxLength(80),
                    Textarea::make('premium.subheadline')->label('Subtítulo')->rows(2)->maxLength(200)->columnSpanFull(),
                    TextInput::make('premium.guarantee')->label('Garantia')->placeholder('Cancele quando quiser')->maxLength(80)
                        ->helperText('Frase curta de segurança exibida junto do botão de assinar.'),
                    TextInput::make('premium.social_proof')->label('Prova social (opcional)')->maxLength(120)
                        ->helperText('Ex.: "Milhares de pessoas rezam com o Sancta todos os dias". Use apenas informações verdadeiras.'),
                    Repeater::make('premium.benefits')
                        ->label('Benefícios (paywall do app e página /planos)')
                        ->columns(3)->reorderable()->collapsible()->cloneable()
                        ->minItems(1)->maxItems(12)
                        ->addActionLabel('Adicionar benefício')
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->schema([
                            Select::make('icon')->label('Ícone')->options(PremiumContent::ICONS)->default(PremiumContent::DEFAULT_ICON)->required()->selectablePlaceholder(false),
                            TextInput::make('title')->label('Título')->required()->maxLength(120)->columnSpan(2),
                            Textarea::make('description')->label('Descrição')->rows(2)->maxLength(300)->columnSpanFull(),
                        ])
                        ->columnSpanFull(),
                    Repeater::make('premium.faq')
                        ->label('Perguntas frequentes do Sancta+')
                        ->reorderable()->collapsible()->cloneable()
                        ->maxItems(20)
                        ->addActionLabel('Adicionar pergunta')
                        ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                        ->schema([
                            TextInput::make('question')->label('Pergunta')->required()->maxLength(200),
                            Textarea::make('answer')->label('Resposta')->required()->rows(3)->maxLength(1000),
                        ])
                        ->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    /** Credenciais (Mercado Pago, Google, Firebase, Gemini, SMTP) e segurança ficam numa página própria. */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('integrations')
                ->label('Integrações e segurança')
                ->icon(Heroicon::OutlinedPuzzlePiece)
                ->color('gray')
                ->url(IntegrationSettings::getUrl())
                ->visible(IntegrationSettings::canAccess()),
        ];
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
                Actions::make([Action::make('save')->label('Salvar configurações')->submit('save')->keyBindings(['mod+s'])]),
            ]);
    }

    public function save(Settings $settings): void
    {
        $state = $this->form->getState();
        $values = [];
        foreach (array_keys(Settings::DEFAULTS) as $key) {
            $values[$key] = data_get($state, $key, Settings::DEFAULTS[$key]);
        }

        // Repeaters devolvem itens com chaves aleatórias: grava como lista simples e já normalizada.
        $values['premium.benefits'] = PremiumContent::normalizeBenefits($values['premium.benefits'] ?? []);
        $values['premium.faq'] = array_values(array_map(
            fn (array $item) => ['question' => trim((string) ($item['question'] ?? '')), 'answer' => trim((string) ($item['answer'] ?? ''))],
            array_filter((array) ($values['premium.faq'] ?? []), 'is_array'),
        ));
        foreach (['premium.subheadline', 'premium.guarantee', 'premium.social_proof'] as $key) {
            $values[$key] = trim((string) ($values[$key] ?? ''));
        }
        foreach (PeriodPrayers::SETTINGS as $key) {
            $values[$key] = filled($values[$key] ?? null) ? (int) $values[$key] : null;
        }

        $before = $settings->all();
        $settings->setMany($values);

        $changed = collect($values)->filter(fn ($v, $k) => ($before[$k] ?? null) !== $v)->all();
        AuditLog::record('settings.updated', null, [], $changed);

        Notification::make()->title('Configurações salvas')->success()->send();
    }
}
