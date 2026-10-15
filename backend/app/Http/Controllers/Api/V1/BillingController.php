<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\UserResource;
use App\Models\Plan;
use App\Services\Billing\GooglePlayBilling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class BillingController extends Controller
{
    /** O app envia o purchaseToken após a compra; o servidor valida no Google e libera o acesso. */
    public function verifyGooglePlay(Request $request, GooglePlayBilling $billing): JsonResponse
    {
        $products = Plan::query()->whereNotNull('google_play_product_id')->distinct()->pluck('google_play_product_id')->all();

        $data = $request->validate([
            'product_id' => ['required', 'string', 'max:120', Rule::in($products)],
            'purchase_token' => ['required', 'string', 'min:20', 'max:4096'],
        ]);

        try {
            $subscription = $billing->verifyAndGrant($request->user(), $data['product_id'], $data['purchase_token']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $user = $request->user()->fresh();

        return response()->json([
            'subscription' => new SubscriptionResource($subscription->load('plan')),
            'user' => new UserResource($user),
        ]);
    }
}
