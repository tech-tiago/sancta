<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChurchResource;
use App\Models\Church;
use App\Services\Community\ChurchSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChurchController extends Controller
{
    /**
     * Lista igrejas para o mapa: por proximidade (lat/lng/raio), por área visível (bbox)
     * ou por busca textual. Sempre paginado/limitado.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'lat' => ['required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['required_with:lat', 'numeric', 'between:-180,180'],
            'radius' => ['sometimes', 'numeric', 'min:1', 'max:300'],
            'bbox' => ['sometimes', 'string', 'regex:/^-?\d+(\.\d+)?,-?\d+(\.\d+)?,-?\d+(\.\d+)?,-?\d+(\.\d+)?$/'],
            'q' => ['sometimes', 'string', 'max:100'],
            'state' => ['sometimes', 'string', 'size:2'],
            'mass_today' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $query = Church::query()->published()->with('schedules');
        $limit = (int) ($data['limit'] ?? 200);

        if (isset($data['lat'], $data['lng']) && in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $query->near((float) $data['lat'], (float) $data['lng'], (float) ($data['radius'] ?? 50));
        } elseif (isset($data['lat'], $data['lng'])) {
            $radius = (float) ($data['radius'] ?? 50);
            $query->whereBetween('latitude', [$data['lat'] - $radius / 111, $data['lat'] + $radius / 111]);
        }

        if (isset($data['bbox'])) {
            [$south, $west, $north, $east] = array_map('floatval', explode(',', $data['bbox']));
            $query->whereBetween('latitude', [min($south, $north), max($south, $north)])
                ->whereBetween('longitude', [min($west, $east), max($west, $east)]);
        }

        $query->when($data['q'] ?? null, function ($q, $term) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('city', 'like', $like)->orWhere('neighborhood', 'like', $like));
        })
            ->when($data['state'] ?? null, fn ($q, $uf) => $q->where('state', strtoupper($uf)))
            ->when($request->boolean('mass_today'), fn ($q) => $q->whereHas('schedules', fn ($s) => $s->where('type', 'missa')->where('weekday', now()->dayOfWeek)));

        if (! isset($data['lat'])) {
            $query->orderByDesc('is_verified')->orderBy('name');
        }

        return ChurchResource::collection($query->limit($limit)->get());
    }

    public function show(string $uuid): ChurchResource
    {
        $church = Church::query()->published()->with('schedules')->where('uuid', $uuid)->firstOrFail();

        return (new ChurchResource($church))->detailed();
    }

    public function favorites(Request $request): AnonymousResourceCollection
    {
        return ChurchResource::collection($request->user()->favoriteChurches()->published()->with('schedules')->get());
    }

    public function toggleFavorite(Request $request, string $uuid): JsonResponse
    {
        $church = Church::query()->published()->where('uuid', $uuid)->firstOrFail();
        $result = $request->user()->favoriteChurches()->toggle([$church->id]);

        return response()->json(['is_favorite' => ! empty($result['attached'])]);
    }

    public function submit(Request $request, ChurchSubmissionService $submissions): JsonResponse
    {
        $data = $request->validate([
            'church_uuid' => ['sometimes', 'nullable', 'uuid', Rule::exists('churches', 'uuid')],
            'name' => ['required_without:church_uuid', 'nullable', 'string', 'min:3', 'max:191'],
            'type' => ['sometimes', 'nullable', Rule::in(['paroquia', 'comunidade', 'capela', 'santuario', 'catedral', 'basilica', 'mosteiro'])],
            'address' => ['required_without:church_uuid', 'nullable', 'string', 'max:255'],
            'neighborhood' => ['sometimes', 'nullable', 'string', 'max:120'],
            'city' => ['required_without:church_uuid', 'nullable', 'string', 'max:120'],
            'state' => ['required_without:church_uuid', 'nullable', 'string', 'size:2'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:12'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'website' => ['sometimes', 'nullable', 'url:https,http', 'max:255'],
            'instagram' => ['sometimes', 'nullable', 'string', 'max:80'],
            'diocese' => ['sometimes', 'nullable', 'string', 'max:191'],
            'mass_times' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'message' => ['required_with:church_uuid', 'nullable', 'string', 'max:2000'],
            'image' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('sancta.security.max_user_upload_kb')],
        ]);

        $submission = $submissions->submit($data, $request->user(), 'app', $request->ip(), $request->file('image'));

        return response()->json([
            'id' => $submission->uuid,
            'message' => 'Obrigado! Sua contribuição será revisada pela nossa equipe antes de aparecer no mapa.',
        ], 201);
    }
}
