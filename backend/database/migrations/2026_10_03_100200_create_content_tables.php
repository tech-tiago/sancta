<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conteúdo espiritual gerenciado pelo painel: categorias, orações (texto e áudio),
 * jornadas (novenas, planos de leitura, desafios), versículo do dia, liturgia,
 * santos do dia, eventos e sessões de oração (sequência de dias).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('color', 9)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('prayers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->string('subtitle', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->json('blocks')->nullable(); // blocos litúrgicos estruturados
            $table->longText('body')->nullable(); // HTML sanitizado (texto livre)
            $table->string('audio_path')->nullable();
            $table->string('audio_url', 500)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('author', 120)->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_premium')->default(false)->index();
            $table->boolean('is_featured')->default(false);
            $table->string('status', 20)->default('published')->index(); // draft | published
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prayer_favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prayer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'prayer_id']);
        });

        Schema::create('journeys', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->string('type', 20)->default('journey'); // journey | novena | reading_plan | challenge
            $table->string('subtitle', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('color', 9)->nullable();
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('status', 20)->default('published')->index();
            $table->date('available_from')->nullable();
            $table->date('available_until')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('journey_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('title', 191);
            $table->text('content')->nullable(); // HTML sanitizado
            $table->string('bible_reference', 120)->nullable(); // ex.: "Jo 3,1-21"
            $table->foreignId('prayer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audio_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->unique(['journey_id', 'position']);
        });

        Schema::create('journey_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journey_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journey_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');

            $table->unique(['user_id', 'journey_step_id']);
            $table->index(['user_id', 'journey_id']);
        });

        Schema::create('daily_verses', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('reference', 60);
            $table->text('text');
            $table->string('reflection_title', 191)->nullable();
            $table->text('reflection')->nullable();
            $table->string('image')->nullable();
            $table->boolean('reflection_is_premium')->default(true);
            $table->timestamps();
        });

        Schema::create('liturgy_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('title', 255)->nullable();
            $table->string('color', 20)->nullable();
            $table->json('readings')->nullable(); // primeira leitura, salmo, segunda, evangelho
            $table->json('prayers')->nullable(); // coleta, oferendas, comunhão
            $table->text('homily')->nullable(); // reflexão (Sancta+)
            $table->string('homily_author', 120)->nullable();
            $table->boolean('is_locked')->default(false); // editado manualmente: não sobrescrever pela API
            $table->string('source', 30)->default('api');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('saints', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('slug', 191)->unique();
            $table->unsignedTinyInteger('feast_month');
            $table->unsignedTinyInteger('feast_day');
            $table->string('title', 191)->nullable(); // "Mártir", "Doutor da Igreja"
            $table->text('biography')->nullable();
            $table->text('prayer')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['feast_month', 'feast_day']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->nullable(); // FK criada após a tabela churches
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('location', 255)->nullable();
            $table->string('link', 500)->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_online')->default(false);
            $table->boolean('is_premium')->default(false);
            $table->string('status', 20)->default('published')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prayer_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('content_type', 30); // prayer | journey_step | bible | liturgy | rosary | free
            $table->unsignedBigInteger('content_id')->nullable();
            $table->unsignedInteger('seconds');
            $table->date('prayed_on');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'prayed_on']);
        });

        Schema::create('prayer_intentions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('text');
            $table->boolean('is_anonymous')->default(true);
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $table->unsignedInteger('prayers_count')->default(0);
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('intention_prayers', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prayer_intention_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'prayer_intention_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intention_prayers');
        Schema::dropIfExists('prayer_intentions');
        Schema::dropIfExists('prayer_sessions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('saints');
        Schema::dropIfExists('liturgy_days');
        Schema::dropIfExists('daily_verses');
        Schema::dropIfExists('journey_progress');
        Schema::dropIfExists('journey_steps');
        Schema::dropIfExists('journeys');
        Schema::dropIfExists('prayer_favorites');
        Schema::dropIfExists('prayers');
        Schema::dropIfExists('categories');
    }
};
