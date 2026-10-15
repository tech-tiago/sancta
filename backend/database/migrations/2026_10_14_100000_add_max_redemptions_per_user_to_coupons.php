<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pentest fase 3 — SEC-11: limite de usos de um cupom POR PESSOA (padrão 1; vazio = sem limite).
 * Cupons já existentes também passam a valer uma vez por pessoa (ajuste no painel, se preciso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedInteger('max_redemptions_per_user')->nullable()->default(1)->after('max_redemptions');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('max_redemptions_per_user');
        });
    }
};
