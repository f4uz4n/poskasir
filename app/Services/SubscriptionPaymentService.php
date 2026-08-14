<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentEmailLog;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionPaymentService
{
    public function generateUnitCode(): string
    {
        $min = config('subscription.unit_code.min', 100);
        $max = config('subscription.unit_code.max', 999);

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $code = str_pad((string) random_int($min, $max), strlen((string) $max), '0', STR_PAD_LEFT);

            $exists = Payment::where('status', 'pending')
                ->where('unit_code', $code)
                ->exists();

            if (! $exists) {
                return $code;
            }
        }

        return (string) random_int($min, $max);
    }

    public function expectedAmount(float $planPrice, string $unitCode): float
    {
        return round($planPrice + (int) $unitCode, 2);
    }

    public function activate(Subscription $subscription, Payment $payment, SubscriptionPlan $plan): void
    {
        DB::transaction(function () use ($subscription, $payment, $plan) {
            $user = $subscription->user;

            $user->subscriptions()
                ->where('status', 'active')
                ->where('id', '!=', $subscription->id)
                ->update(['status' => 'expired']);

            $subscription->update([
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => $plan->is_free ? null : now()->addDays($plan->duration_days),
            ]);

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'email_verified_at' => $payment->email_verified_at ?? now(),
            ]);
        });
    }

    public function expireOldPendingPayments(): int
    {
        return Payment::where('status', 'pending')
            ->where(function ($q) {
                $q->where('expires_at', '<', now())
                    ->orWhere(function ($inner) {
                        $hours = config('subscription.payment_expires_hours', 48);
                        $inner->whereNull('expires_at')
                            ->where('created_at', '<', now()->subHours($hours));
                    });
            })
            ->update(['status' => 'expired']);
    }
}
