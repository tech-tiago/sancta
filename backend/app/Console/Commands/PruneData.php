<?php

namespace App\Console\Commands;

use App\Models\AnnouncementInteraction;
use App\Models\NewsArticle;
use App\Models\WebhookEvent;
use Illuminate\Console\Command;

/** Limpeza periódica (LGPD: minimização de dados + desempenho). */
class PruneData extends Command
{
    protected $signature = 'sancta:prune';

    protected $description = 'Remove notícias antigas importadas, interações de avisos e webhooks antigos';

    public function handle(): int
    {
        $news = NewsArticle::query()
            ->whereNotNull('news_source_id')
            ->where('is_featured', false)
            ->where('published_at', '<', now()->subDays(config('sancta.news.keep_days', 90)))
            ->delete();

        $interactions = AnnouncementInteraction::query()->where('created_at', '<', now()->subDays(180))->delete();
        $webhooks = WebhookEvent::query()->where('created_at', '<', now()->subDays(365))->where('status', '!=', 'failed')->delete();

        $this->info("Notícias: {$news} · Interações: {$interactions} · Webhooks: {$webhooks}");

        return self::SUCCESS;
    }
}
