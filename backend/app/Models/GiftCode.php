<?php

namespace App\Models;

use App\Enums\GiftCodeStatus;
use App\Models\Concerns\HasUuid;
use App\Support\GiftCodeFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Código de presente Sancta+ (comprado no site). O código em claro NUNCA é gravado:
 * só o HMAC (code_hash, oculto) e os 4 últimos caracteres. Criado/alterado apenas
 * pelo App\Services\Billing\GiftService.
 */
class GiftCode extends Model
{
    use HasUuid;

    /** Validade do código a partir da compra. */
    public const VALID_MONTHS = 12;

    protected $guarded = ['*'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'status' => GiftCodeStatus::class,
            'redeemed_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_id')->withTrashed();
    }

    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by')->withTrashed();
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** Status considerando a validade (um código "disponível" vencido aparece como expirado). */
    public function effectiveStatus(): GiftCodeStatus
    {
        if ($this->status === GiftCodeStatus::Available && $this->expires_at?->isPast()) {
            return GiftCodeStatus::Expired;
        }

        return $this->status;
    }

    public function isRedeemable(): bool
    {
        return $this->effectiveStatus() === GiftCodeStatus::Available;
    }

    /** Pode ser revogado no painel (disponível ou já resgatado). */
    public function isRevocable(): bool
    {
        return in_array($this->status, [GiftCodeStatus::Available, GiftCodeStatus::Redeemed], true);
    }

    public function maskedCode(): string
    {
        return GiftCodeFormat::masked((string) $this->last4);
    }
}
