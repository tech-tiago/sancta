<?php

namespace App\Http\Resources;

use App\Enums\ScheduleType;
use App\Models\Church;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Church */
class ChurchResource extends JsonResource
{
    private bool $detailed = false;

    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->uuid,
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->getLabel(),
            'city' => $this->city,
            'state' => $this->state,
            'address' => $this->fullAddress(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_verified' => $this->is_verified,
            'cover_url' => $this->coverUrl(),
            'distance_km' => isset($this->distance_km) ? round((float) $this->distance_km, 1) : null,
            'next_mass' => $this->relationLoaded('schedules') ? $this->nextMass() : null,
        ];

        if (! $this->detailed) {
            return $data;
        }

        $user = $request->user('sanctum');

        return $data + [
            'description' => $this->description,
            'diocese' => $this->diocese,
            'patron' => $this->patron,
            'neighborhood' => $this->neighborhood,
            'postal_code' => $this->postal_code,
            // `contacts` e `schedules` são mapas: vazios saem como `{}` (o app lê como objeto, nunca lista).
            'contacts' => (object) array_filter([
                'phone' => $this->phone,
                'whatsapp' => $this->whatsapp,
                'email' => $this->email,
                'website' => $this->website,
                'instagram' => $this->instagram,
                'facebook' => $this->facebook,
                'youtube' => $this->youtube,
            ]),
            'maps_url' => $this->mapsUrl(),
            'schedules' => (object) collect(ScheduleType::cases())->mapWithKeys(fn (ScheduleType $type) => [
                $type->value => $this->schedules->where('type', $type)->values()->map(fn ($s) => [
                    'weekday' => $s->weekday,
                    'weekday_label' => ScheduleType::weekdayLabel($s->weekday),
                    'starts_at' => $s->starts_at ? substr($s->starts_at, 0, 5) : null,
                    'ends_at' => $s->ends_at ? substr($s->ends_at, 0, 5) : null,
                    'notes' => $s->notes,
                    'label' => $s->label(),
                ]),
            ])->filter(fn ($list) => $list->isNotEmpty())->all(),
            'is_favorite' => $user ? $user->favoriteChurches()->whereKey($this->id)->exists() : false,
        ];
    }

    private function nextMass(): ?array
    {
        $now = now();
        $masses = $this->schedules->where('type', ScheduleType::Missa)->filter(fn ($s) => $s->weekday !== null && $s->starts_at);

        for ($offset = 0; $offset < 7; $offset++) {
            $day = $now->copy()->addDays($offset);
            $candidates = $masses->where('weekday', $day->dayOfWeek)
                ->filter(fn ($s) => $offset > 0 || substr($s->starts_at, 0, 5) >= $now->format('H:i'))
                ->sortBy('starts_at');

            if ($first = $candidates->first()) {
                return [
                    'weekday' => $first->weekday,
                    'time' => substr($first->starts_at, 0, 5),
                    'label' => ($offset === 0 ? 'Hoje' : ($offset === 1 ? 'Amanhã' : ScheduleType::weekdayLabel($first->weekday))).' às '.substr($first->starts_at, 0, 5),
                ];
            }
        }

        return null;
    }
}
