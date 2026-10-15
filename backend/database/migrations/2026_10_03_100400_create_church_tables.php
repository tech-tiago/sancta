<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Igrejas: cadastro, horários estruturados (missa, confissão, adoração...),
 * favoritos e sugestões/correções enviadas por usuários (moderadas no painel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 191);
            $table->string('slug', 191)->unique();
            $table->string('type', 20)->default('paroquia'); // paroquia | comunidade | capela | santuario | catedral | basilica | mosteiro
            $table->string('diocese', 191)->nullable();
            $table->string('patron', 191)->nullable(); // padroeiro(a)
            $table->text('description')->nullable();
            $table->string('address', 255)->nullable();
            $table->string('neighborhood', 120)->nullable();
            $table->string('city', 120)->nullable()->index();
            $table->char('state', 2)->nullable()->index();
            $table->string('country', 60)->default('Brasil');
            $table->string('postal_code', 12)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('instagram', 80)->nullable();
            $table->string('facebook', 255)->nullable();
            $table->string('youtube', 255)->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->string('status', 20)->default('published')->index(); // published | hidden
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['latitude', 'longitude']);
        });

        Schema::create('church_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // missa | confissao | adoracao | secretaria | terco | outro
            $table->unsignedTinyInteger('weekday')->nullable(); // 0=domingo ... 6=sábado; null = texto livre
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('notes', 191)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['church_id', 'type', 'weekday']);
        });

        Schema::create('church_favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->boolean('notify')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'church_id']);
        });

        Schema::create('church_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 20); // new | correction
            $table->foreignId('church_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('submitter_name', 120)->nullable();
            $table->string('submitter_email', 191)->nullable();
            $table->json('payload'); // campos sugeridos (validados)
            $table->text('message')->nullable(); // descrição do erro / observações
            $table->string('image_path')->nullable(); // disco privado até aprovação
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_notes', 500)->nullable();
            $table->string('channel', 10)->default('app'); // app | site
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreign('church_id')->references('id')->on('churches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['church_id']);
        });
        Schema::dropIfExists('church_submissions');
        Schema::dropIfExists('church_favorites');
        Schema::dropIfExists('church_schedules');
        Schema::dropIfExists('churches');
    }
};
