<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Settings;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail ao COMPRADOR com o código de presente Sancta+.
 *
 * Propositalmente NÃO implementa ShouldQueue: o código em claro não pode ser serializado
 * na fila (tabela jobs). O envio é feito logo após a transação (GiftService).
 */
class GiftCodeIssued extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly string $planName,
        public readonly CarbonInterface $expiresAt,
        public readonly bool $regenerated = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $premium = (string) app(Settings::class)->get('premium.name');
        $name = $notifiable instanceof User ? $notifiable->firstName() : '';

        return (new MailMessage)
            ->subject($this->regenerated ? "Seu novo código de presente {$premium}" : "Seu presente {$premium} está pronto")
            ->greeting(trim("Olá, {$name}"))
            ->line($this->regenerated
                ? 'Geramos um novo código para o seu presente. O código anterior deixou de valer.'
                : 'Obrigado por presentear alguém com o '.$premium.'. Este é o código do presente:')
            ->line('**'.$this->code.'**')
            ->line("Plano: {$premium} {$this->planName} · resgate até ".$this->expiresAt->format('d/m/Y').'.')
            ->action('Como resgatar', route('gift.redeem'))
            ->line('Quem receber o presente deve entrar (ou criar uma conta) no site e informar o código em "Resgatar presente". O acesso vale no app e no site, com a mesma conta.')
            ->line('Por segurança, não guardamos o código. Guarde este e-mail; se perdê-lo, gere um novo código em Minha conta → Presentes (o anterior deixa de valer).')
            ->salutation('Que Deus abençoe você e quem vai receber este presente.');
    }
}
