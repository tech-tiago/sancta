<?php

namespace App\Console\Commands;

use App\Models\PushCampaign;
use App\Services\Push\PushService;
use Illuminate\Console\Command;

class SendScheduledPush extends Command
{
    protected $signature = 'sancta:send-scheduled-push';

    protected $description = 'Envia as campanhas de push agendadas cujo horário chegou';

    public function handle(PushService $push): int
    {
        PushCampaign::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(20)
            ->get()
            ->each(fn (PushCampaign $c) => $this->line($c->title.': '.($push->sendCampaign($c) ? 'enviada' : 'falhou')));

        return self::SUCCESS;
    }
}
