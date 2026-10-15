<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'plan' => $this->plan?->name,
            'plan_slug' => $this->plan?->slug,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'provider' => $this->provider->value,
            'auto_renew' => $this->auto_renew,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'manage_url' => $this->provider->value === 'google_play'
                ? 'https://play.google.com/store/account/subscriptions?package='.config('services.google_play.package_name')
                : route('account.show'),
        ];
    }
}
