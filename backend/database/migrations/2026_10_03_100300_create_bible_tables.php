<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bíblia: versões, livros, versículos (com busca full-text no MySQL),
 * anotações do usuário (destaque, nota, marcador), progresso de leitura
 * e cache de explicações geradas por IA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bible_versions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('abbreviation', 20)->unique();
            $table->string('language', 10)->default('pt_BR');
            $table->string('license_notice', 500)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bible_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bible_version_id')->constrained()->cascadeOnDelete();
            $table->string('testament', 2); // AT | NT
            $table->unsignedTinyInteger('position');
            $table->string('name', 60);
            $table->string('abbreviation', 10);
            $table->string('slug', 60);
            $table->unsignedSmallInteger('chapters_count')->default(0);
            $table->timestamps();

            $table->unique(['bible_version_id', 'slug']);
            $table->unique(['bible_version_id', 'position']);
        });

        Schema::create('bible_verses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bible_book_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('chapter');
            $table->unsignedSmallInteger('verse');
            $table->text('text');

            $table->unique(['bible_book_id', 'chapter', 'verse']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE bible_verses ADD FULLTEXT INDEX bible_verses_text_fulltext (text)');
        }

        Schema::create('bible_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bible_verse_id')->constrained()->cascadeOnDelete();
            $table->string('highlight_color', 20)->nullable(); // gold | blue | green | rose | lilac
            $table->text('note')->nullable();
            $table->boolean('is_bookmarked')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'bible_verse_id']);
        });

        Schema::create('reading_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('bible_book_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('chapter');
            $table->unsignedSmallInteger('verse')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_explanations', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 64)->unique(); // sha256(referência + texto)
            $table->string('reference', 120);
            $table->text('content');
            $table->string('model', 60);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_explanations');
        Schema::dropIfExists('reading_progress');
        Schema::dropIfExists('bible_annotations');
        Schema::dropIfExists('bible_verses');
        Schema::dropIfExists('bible_books');
        Schema::dropIfExists('bible_versions');
    }
};
