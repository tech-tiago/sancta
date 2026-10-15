<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasUuid;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pagamentos não usam o trait Auditable: a trilha de auditoria de
 * pagamentos é a tabela webhook_events + os próprios registros (imutáveis).
 */
class Payment extends Model
{
    use HasUuid;

    // Nenhum campo é preenchível em massa: pagamentos só são criados/alterados
    // pelos serviços de cobrança (CheckoutService / processadores de webhook).
    protected $guarded = ['*'];

    protected $hidden = ['pix_qr_code_base64', 'metadata', 'ip_address'];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'metadata' => 'array',
            'is_gift' => 'boolean',
        ];
    }

    /** Código gerado quando o pagamento é de um presente (is_gift). */
    public function giftCode(): HasOne
    {
        return $this->hasOne(GiftCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function formattedAmount(): string
    {
        return Money::format($this->amount_cents);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }
}
