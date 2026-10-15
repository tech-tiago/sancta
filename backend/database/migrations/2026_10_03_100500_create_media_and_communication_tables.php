<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notícias (RSS + manuais), canais ao vivo (YouTube), avisos/pop-ups,
 * campanhas de push e páginas institucionais (CMS, FAQ, depoimentos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('type', 10)->default('rss'); // rss | manual
            $table->string('url', 500)->nullable(); // feed RSS
            $table->string('website', 255)->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('fetch_interval_minutes')->default(60);
            $table->unsignedSmallInteger('max_items')->default(30);
            $table->timestamp('last_fetched_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 255);
            $table->string('slug', 191)->unique();
            $table->text('summary')->nullable();
            $table->longText('content')->nullable(); // HTML sanitizado (artigos manuais)
            $table->string('image_url', 500)->nullable();
            $table->string('image')->nullable();
            $table->string('url', 500)->nullable();
            $table->char('url_hash', 64)->nullable()->unique(); // sha256 do link original (deduplicação)
            $table->string('author', 120)->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_featured')->default(false);
            $table->string('status', 20)->default('published')->index(); // published | hidden
            $table->timestamps();
        });

        Schema::create('live_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->string('type', 20)->default('live'); // live | video | playlist | channel
            $table->string('youtube_url', 500);
            $table->string('fallback_url', 500)->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->text('body')->nullable();
            $table->string('image')->nullable();
            $table->string('kind', 20)->default('popup'); // popup | banner | fullscreen
            $table->string('category', 20)->default('notice'); // notice | event | promotion | update
            $table->string('cta_label', 40)->nullable();
            $table->string('cta_url', 500)->nullable(); // URL https ou rota interna (sancta://...)
            $table->string('audience', 20)->default('all'); // all | free | premium | guests
            $table->string('platform', 10)->default('app'); // app | site | both
            $table->string('frequency', 20)->default('once'); // once | daily | every_open
            $table->unsignedTinyInteger('priority')->default(0);
            $table->boolean('dismissible')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('action', 10); // view | click | dismiss
            $table->timestamp('created_at')->useCurrent();

            $table->index(['announcement_id', 'action']);
        });

        Schema::create('push_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('body', 500);
            $table->string('image_url', 500)->nullable();
            $table->string('deep_link', 255)->nullable(); // sancta://liturgia, sancta://oracao/slug...
            $table->string('audience', 20)->default('all'); // all | free | premium
            $table->string('status', 20)->default('draft')->index(); // draft | scheduled | sending | sent | failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('title', 191);
            $table->longText('content')->nullable(); // HTML sanitizado
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 255);
            $table->text('answer');
            $table->string('audience', 10)->default('both'); // app | site | both
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('author', 120);
            $table->string('location', 120)->nullable();
            $table->text('content');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('push_campaigns');
        Schema::dropIfExists('announcement_interactions');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('live_channels');
        Schema::dropIfExists('news_articles');
        Schema::dropIfExists('news_sources');
    }
};
