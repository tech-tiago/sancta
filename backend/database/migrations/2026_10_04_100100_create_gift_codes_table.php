<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Presentear Sancta+" (somente no site): um pagamento marcado como presente gera um
 * código SANC-XXXX-XXXX-XXXX. O código NUNCA é gravado: só o HMAC-SHA256 (com a APP_KEY)
 * e os 4 últimos caracteres, para exibição.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_gift')->default(false)->after('coupon_id');
        });

        Schema::create('gift_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->char('code_hash', 64)->unique();
            $table->char('last4', 4);
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchaser_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 12)->default('available')->index(); // available | redeemed | revoked | expired
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            // Período concedido a quem resgatou (revogado em caso de estorno/chargeback).
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['purchaser_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_codes');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('is_gift');
        });
    }
};
