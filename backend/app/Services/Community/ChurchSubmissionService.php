<?php

namespace App\Services\Community;

use App\Enums\ModerationStatus;
use App\Enums\ScheduleType;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\ChurchSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sugestões e correções de igrejas enviadas pelo app/site.
 * Fluxo: envio → fila "Pendente" no painel → aprovação cria/atualiza a igreja (aparece no mapa).
 */
class ChurchSubmissionService
{
    /** Campos que o público pode sugerir (lista branca). */
    public const FIELDS = [
        'name', 'type', 'address', 'neighborhood', 'city', 'state', 'postal_code', 'latitude', 'longitude',
        'phone', 'whatsapp', 'email', 'website', 'instagram', 'diocese', 'mass_times',
    ];

    public function submit(array $data, ?User $user, string $channel, ?string $ip, ?UploadedFile $image = null): ChurchSubmission
    {
        $payload = Arr::only($data, self::FIELDS);
        $payload = array_map(fn ($v) => is_string($v) ? Str::limit(strip_tags(trim($v)), 500, '') : $v, $payload);

        $imagePath = null;
        if ($image) {
            // Disco PRIVADO até a aprovação; nome aleatório; extensão derivada do conteúdo (não do nome enviado).
            $imagePath = $image->storeAs('submissions', Str::uuid().'.'.$image->guessExtension(), 'local');
        }

        $submission = new ChurchSubmission([
            'type' => isset($data['church_uuid']) ? 'correction' : 'new',
            'submitter_name' => $user?->name ?? Str::limit(strip_tags((string) ($data['submitter_name'] ?? '')), 120, ''),
            'submitter_email' => $user?->email ?? (isset($data['submitter_email']) ? mb_strtolower(trim($data['submitter_email'])) : null),
            'payload' => $payload,
            'message' => isset($data['message']) ? Str::limit(strip_tags($data['message']), 2000, '') : null,
            'image_path' => $imagePath,
            'channel' => $channel,
            'ip_address' => $ip,
        ]);

        if (isset($data['church_uuid'])) {
            $submission->church_id = Church::query()->where('uuid', $data['church_uuid'])->value('id');
        }
        $submission->user_id = $user?->id;
        $submission->save();

        return $submission;
    }

    public function approve(ChurchSubmission $submission, User $reviewer, array $overrides = [], ?string $notes = null): Church
    {
        return DB::transaction(function () use ($submission, $reviewer, $overrides, $notes): Church {
            $data = array_filter(array_merge($submission->payload ?? [], $overrides), fn ($v) => $v !== null && $v !== '');
            $massTimes = $data['mass_times'] ?? null;
            $attributes = Arr::only($data, (new Church)->getFillable());

            if ($submission->type === 'correction' && $submission->church) {
                $church = $submission->church;
                $church->fill($attributes)->save();
            } else {
                $church = new Church($attributes + ['status' => 'published']);
                $church->created_by = $submission->user_id;
                $church->save();

                if (is_string($massTimes) && trim($massTimes) !== '') {
                    $church->schedules()->create([
                        'type' => ScheduleType::Missa,
                        'weekday' => null,
                        'notes' => Str::limit($massTimes, 190, ''),
                    ]);
                }
            }

            if ($submission->image_path && Storage::disk('local')->exists($submission->image_path)) {
                $public = 'churches/'.basename($submission->image_path);
                Storage::disk('public')->put($public, Storage::disk('local')->get($submission->image_path));
                $church->forceFill(['cover_image' => $public])->save();
            }

            $submission->forceFill([
                'status' => ModerationStatus::Approved,
                'church_id' => $church->id,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ])->save();

            AuditLog::record('church_submission.approved', $submission, [], ['church_id' => $church->id], $reviewer);

            return $church;
        });
    }

    public function reject(ChurchSubmission $submission, User $reviewer, ?string $notes = null): void
    {
        $submission->forceFill([
            'status' => ModerationStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ])->save();

        if ($submission->image_path) {
            Storage::disk('local')->delete($submission->image_path);
        }

        AuditLog::record('church_submission.rejected', $submission, [], ['notes' => $notes], $reviewer);
    }
}
