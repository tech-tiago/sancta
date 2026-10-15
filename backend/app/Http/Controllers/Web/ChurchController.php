<?php

namespace App\Http\Controllers\Web;

use App\Enums\ChurchType;
use App\Enums\ScheduleType;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\Community\ChurchSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Mapa público de igrejas + formulários de sugestão/correção.
 * Proteções anti-spam: limite por IP, campo "honeypot" e tempo mínimo de preenchimento.
 */
class ChurchController extends Controller
{
    public function index(Request $request): View
    {
        return view('site.churches.index', [
            'total' => Church::query()->published()->count(),
            'states' => Church::query()->published()->whereNotNull('state')->distinct()->orderBy('state')->pluck('state'),
        ]);
    }

    /** Dados enxutos para os marcadores do mapa (cacheados). */
    public function mapData(): JsonResponse
    {
        $data = cache()->remember('site.churches.map', 600, fn () => Church::query()->published()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->get(['name', 'slug', 'type', 'city', 'state', 'latitude', 'longitude', 'is_verified', 'address', 'neighborhood'])
            ->map(fn (Church $c) => [
                'n' => $c->name,
                's' => $c->slug,
                't' => $c->type->getLabel(),
                'c' => trim(($c->city ?? '').($c->state ? '/'.$c->state : '')),
                'a' => $c->fullAddress(),
                'lat' => $c->latitude,
                'lng' => $c->longitude,
                'v' => $c->is_verified,
            ])->values()->all());

        return response()->json($data)->setPublic()->setMaxAge(300);
    }

    public function show(string $slug): View
    {
        $church = Church::query()->published()->with('schedules')->where('slug', $slug)->firstOrFail();

        $schedules = collect(ScheduleType::cases())
            ->mapWithKeys(fn (ScheduleType $type) => [$type->getLabel() => $church->schedules->where('type', $type)->values()])
            ->filter(fn ($list) => $list->isNotEmpty());

        return view('site.churches.show', compact('church', 'schedules'));
    }

    public function create(): View
    {
        return view('site.churches.form', ['church' => null, 'types' => ChurchType::cases(), 'startedAt' => encrypt(now()->timestamp)]);
    }

    public function correct(string $slug): View
    {
        $church = Church::query()->published()->where('slug', $slug)->firstOrFail();

        return view('site.churches.form', ['church' => $church, 'types' => ChurchType::cases(), 'startedAt' => encrypt(now()->timestamp)]);
    }

    public function store(Request $request, ChurchSubmissionService $service): RedirectResponse
    {
        $this->guardAgainstBots($request);

        $data = $request->validate($this->rules(false));
        $service->submit($data, $request->user(), 'site', $request->ip(), $request->file('image'));

        return redirect()->route('churches.index')->with('status', 'Obrigado! Sua sugestão foi enviada e será revisada pela nossa equipe antes de aparecer no mapa.');
    }

    public function storeCorrection(Request $request, string $slug, ChurchSubmissionService $service): RedirectResponse
    {
        $this->guardAgainstBots($request);

        $church = Church::query()->published()->where('slug', $slug)->firstOrFail();
        $data = $request->validate($this->rules(true));
        $data['church_uuid'] = $church->uuid;

        $service->submit($data, $request->user(), 'site', $request->ip(), $request->file('image'));

        return redirect()->route('churches.show', $church->slug)->with('status', 'Obrigado! A correção foi enviada para revisão.');
    }

    private function rules(bool $correction): array
    {
        $required = $correction ? 'nullable' : 'required';

        return [
            'name' => [$required, 'string', 'min:3', 'max:191'],
            'type' => ['nullable', Rule::enum(ChurchType::class)],
            'address' => [$required, 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'city' => [$required, 'string', 'max:120'],
            'state' => [$required, 'string', 'size:2', 'alpha'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'website' => ['nullable', 'url:https,http', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:80'],
            'mass_times' => ['nullable', 'string', 'max:1000'],
            'message' => [$correction ? 'required' : 'nullable', 'string', 'max:2000'],
            'submitter_name' => [auth()->check() ? 'nullable' : 'required', 'string', 'max:120'],
            'submitter_email' => [auth()->check() ? 'nullable' : 'required', 'email', 'max:191'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('sancta.security.max_user_upload_kb')],
            'consent' => ['accepted'],
        ];
    }

    private function guardAgainstBots(Request $request): void
    {
        // Honeypot: campo invisível que humanos não preenchem.
        if (filled($request->input('website_confirm'))) {
            throw ValidationException::withMessages(['name' => 'Não foi possível enviar. Tente novamente.']);
        }

        // Envio rápido demais (< 4 s) indica automação.
        try {
            $started = (int) decrypt((string) $request->input('_started'));
        } catch (\Throwable) {
            $started = 0;
        }

        if ($started === 0 || now()->timestamp - $started < 4) {
            throw ValidationException::withMessages(['name' => 'Por favor, revise os dados e envie novamente.']);
        }
    }
}
