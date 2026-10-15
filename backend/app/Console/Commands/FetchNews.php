<?php

namespace App\Console\Commands;

use App\Models\NewsSource;
use App\Services\Content\NewsFetcher;
use Illuminate\Console\Command;

class FetchNews extends Command
{
    protected $signature = 'sancta:fetch-news {--force : Ignora o intervalo configurado}';

    protected $description = 'Importa notícias das fontes RSS ativas';

    public function handle(NewsFetcher $fetcher): int
    {
        NewsSource::query()->where('is_active', true)->where('type', 'rss')->get()
            ->filter(fn (NewsSource $s) => $this->option('force') || $s->isDue())
            ->each(function (NewsSource $source) use ($fetcher): void {
                $count = $fetcher->fetch($source);
                $source->refresh();
                $this->line(sprintf('%-30s %s', $source->name, $source->last_error ? "erro: {$source->last_error}" : "{$count} novas"));
            });

        return self::SUCCESS;
    }
}
