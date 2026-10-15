<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LiveChannel;
use App\Services\Live\LiveDirectory;
use App\Services\Settings;
use Illuminate\View\View;

/** Página pública /ao-vivo: ao vivo agora, agenda de hoje e canais (abre o YouTube em nova aba). */
class LiveController extends Controller
{
    public function index(LiveDirectory $directory, Settings $settings): View
    {
        abort_unless($settings->bool('features.live'), 404);

        $channels = $directory->channels();
        $now = $directory->now();

        return view('site.live.index', [
            'liveNow' => $channels->filter(fn (LiveChannel $c) => $c->isLive() || $c->isOnScheduleNow($now))->values(),
            'today' => $directory->todaySchedule($channels),
            'channels' => $channels,
            'now' => $now,
        ]);
    }
}
