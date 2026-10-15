<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120);
            $table->string('email', 191)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(); // nulo para contas somente Google
            $table->string('google_id', 64)->nullable()->unique();
            $table->string('avatar_path')->nullable();
            $table->string('avatar_url', 500)->nullable(); // foto do Google

            // Papel e situação — NUNCA preenchíveis em massa
            $table->string('role', 20)->default('user')->index();
            $table->string('status', 20)->default('active')->index();
            $table->string('suspension_reason', 500)->nullable();

            // Preferências
            $table->string('theme', 20)->default('system');
            $table->string('locale', 10)->default('pt_BR');
            $table->json('notification_preferences')->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamp('terms_accepted_at')->nullable();

            // Jornada espiritual (sequência de dias, tempo de oração)
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('last_prayed_on')->nullable();
            $table->unsignedBigInteger('total_prayer_seconds')->default(0);

            // Segurança
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('source', 20)->default('app'); // app | site | admin | import

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 191)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id', 128)->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
