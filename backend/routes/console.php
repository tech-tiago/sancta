<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Agendador do Sancta
|--------------------------------------------------------------------------
| No servidor, configure UMA entrada no cron:
|   * * * * * cd /caminho/do/sancta/backend && php artisan schedule:run >> /dev/null 2>&1
*/

Schedule::command('sancta:fetch-news')->everyFifteenMinutes()->withoutOverlapping()->runInBackground();
Schedule::command('sancta:prefetch-liturgy --days=7')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('sancta:subscriptions-maintenance')->hourly()->withoutOverlapping();
Schedule::command('sancta:send-scheduled-push')->everyMinute()->withoutOverlapping();
// Ao vivo: RSS + YouTube Data API (~1 unidade de cota por lote de 50 vídeos).
Schedule::command('sancta:live-sync')->everyThreeMinutes()->withoutOverlapping(10);
Schedule::command('sancta:prune')->dailyAt('03:30');
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
