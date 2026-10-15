<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assinaturas (Sancta+): planos, cupons, assinaturas, pagamentos e webhooks.
 * Valores monetários sempre em CENTAVOS (inteiro) para evitar erro de arredondamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
            $table->string('description', 500)->nullable();
            $table->string('interval', 10)->default('month'); // month | year | lifetime
            $table->unsignedSmallInteger('interval_count')->default(1);
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('compare_at_price_cents')->nullable(); // preço "de"
            $table->char('currency', 3)->default('BRL');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedTinyInteger('max_installments')->default(1);
            $table->string('google_play_product_id', 120)->nullable();
            $table->string('google_play_base_plan_id', 120)->nullable();
            $table->json('features')->nullable();
            $table->string('badge', 40)->nullable(); // "Mais popular", "Economize 37%"
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('available_on_web')->default(true);
            $table->boolean('available_on_app')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description', 255)->nullable();
            $table->string('type', 10)->default('percent'); // percent | fixed
            $table->unsignedInteger('value'); // % (1-100) ou centavos
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->json('plan_ids')->nullable(); // null = todos os planos
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20); // google_play | mercadopago | manual | fake
            $table->string('status', 20)->index(); // pending | trialing | active | past_due | canceled | expired | refunded
            $table->string('provider_reference', 191)->nullable(); // orderId / id externo
            $table->string('purchase_token_hash', 64)->nullable()->unique(); // sha256 do purchaseToken (Google Play)
            $table->text('purchase_token')->nullable(); // criptografado (cast encrypted)
            $table->boolean('auto_renew')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'ends_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20); // mercadopago | google_play | manual | fake
            $table->string('method', 20); // pix | credit_card | google_play | manual
            $table->string('status', 20)->index(); // pending | approved | rejected | refunded | canceled | expired | charged_back
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('discount_cents')->default(0);
            $table->char('currency', 3)->default('BRL');
            $table->unsignedTinyInteger('installments')->default(1);
            $table->string('provider_payment_id', 120)->nullable();
            $table->string('provider_preference_id', 120)->nullable();
            $table->text('checkout_url')->nullable();
            $table->text('pix_qr_code')->nullable(); // "copia e cola"
            $table->longText('pix_qr_code_base64')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('refunded_at')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_payment_id']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('event_id', 191);
            $table->string('type', 100)->nullable();
            $table->string('status', 20)->default('received'); // received | processed | ignored | failed
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('plans');
    }
};
