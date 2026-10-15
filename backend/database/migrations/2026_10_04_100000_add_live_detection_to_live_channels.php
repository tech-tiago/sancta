<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ao vivo: canal do YouTube (UC…), categoria, capa, status detectado automaticamente
 * (comando sancta:live-sync) e agenda de transmissões por canal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_channels', function (Blueprint $table) {
            $table->string('youtube_channel_id', 24)->nullable()->after('youtube_url')->index();
            $table->string('category', 20)->default('canal')->after('type'); // missa | terco | adoracao | oracao | canal
            $table->string('cover')->nullable()->after('logo');

            // Status mantido pelo comando sancta:live-sync (nunca editado no painel).
            $table->string('live_status', 10)->default('none')->after('sort_order'); // none | upcoming | live
            $table->string('live_video_id', 20)->nullable()->after('live_status');
            $table->string('live_title', 255)->nullable()->after('live_video_id');
            $table->timestamp('live_started_at')->nullable()->after('live_title');
            $table->timestamp('live_scheduled_at')->nullable()->after('live_started_at');
            $table->timestamp('live_checked_at')->nullable()->after('live_scheduled_at');
            $table->string('last_notified_video_id', 20)->nullable()->after('live_checked_at');
        });

        Schema::create('live_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_channel_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday')->nullable(); // 0=domingo … 6=sábado; null = todos os dias
            $table->time('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('title', 120)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['live_channel_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_schedules');

        Schema::table('live_channels', function (Blueprint $table) {
            $table->dropIndex(['youtube_channel_id']);
            $table->dropColumn([
                'youtube_channel_id', 'category', 'cover', 'live_status', 'live_video_id', 'live_title',
                'live_started_at', 'live_scheduled_at', 'live_checked_at', 'last_notified_video_id',
            ]);
        });
    }
};
