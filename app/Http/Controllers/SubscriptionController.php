<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\RecaptchaService;
use App\Services\SubscriptionEmailVerifier;
use App\Services\SubscriptionPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionPaymentService $paymentService,
        private SubscriptionEmailVerifier $emailVerifier,
    ) {}

    public function index(RecaptchaService $recaptcha)
    {
        $user = Auth::user();
        abort_unless($user->isStoreOwner(), 403);

        $owner = $user->storeOwner();
        $plans = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();
        $current = $owner->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest()
            ->first();
        $payments = $owner->payments()->with('subscription.plan')->latest()->limit(10)->get();
        $recaptchaEnabled = $recaptcha->shouldChallenge(request());
        $emailAutoVerify = $this->emailVerifier->isConfigured();

        return view('subscription.index', compact('plans', 'current', 'payments', 'recaptchaEnabled', 'emailAutoVerify'));
    }

    public function subscribe(Request $request, RecaptchaService $recaptcha)
    {
        abort_unless(Auth::user()->isStoreOwner(), 403);

        $data = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'method' => ['required', 'in:transfer,demo'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payer_bank' => ['nullable', 'string', 'max:100'],
        ]);

        $plan = SubscriptionPlan::findOrFail($data['plan_id']);

        if (! $plan->is_free && (float) $plan->price > 0) {
            $recaptcha->verifyOrFail($request);
            $data['method'] = 'transfer';
        } else {
            $data['method'] = 'demo';
        }

        $user = Auth::user()->storeOwner();
        $hours = config('subscription.payment_expires_hours', 48);

        $result = DB::transaction(function () use ($plan, $user, $data, $hours) {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'pending',
            ]);

            $unitCode = null;
            $expectedAmount = (float) $plan->price;

            if ($data['method'] === 'transfer' && (float) $plan->price > 0) {
                $unitCode = $this->paymentService->generateUnitCode();
                $expectedAmount = $this->paymentService->expectedAmount((float) $plan->price, $unitCode);
            }

            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'invoice_code' => 'SUB-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5)),
                'amount' => $plan->price,
                'unit_code' => $unitCode,
                'expected_amount' => $expectedAmount,
                'method' => $data['method'],
                'status' => 'pending',
                'payer_name' => $data['payer_name'] ?? $user->name,
                'payer_bank' => $data['payer_bank'] ?? null,
                'expires_at' => (float) $plan->price > 0 ? now()->addHours($hours) : null,
            ]);

            if ($data['method'] === 'demo' || (float) $plan->price === 0.0) {
                $this->paymentService->activate($subscription, $payment, $plan);
            }

            return compact('subscription', 'payment');
        });

        if ($result['payment']->status === 'paid') {
            return redirect()->route('subscription.index')
                ->with('success', 'Langganan berhasil diaktifkan.');
        }

        return redirect()->route('subscription.payment', $result['payment'])
            ->with('success', 'Silakan transfer sesuai nominal + kode unit untuk validasi otomatis.');
    }

    public function payment(Payment $payment)
    {
        abort_unless($payment->user_id === Auth::id(), 403);
        $payment->load('subscription.plan');
        $bank = config('subscription.bank');
        $emailReady = $this->emailVerifier->isConfigured() && $this->emailVerifier->extensionAvailable();

        return view('subscription.payment', compact('payment', 'bank', 'emailReady'));
    }

    public function verifyPayment(Payment $payment)
    {
        abort_unless($payment->user_id === Auth::id(), 403);

        $result = $this->emailVerifier->verifySingle($payment);

        if (request()->expectsJson()) {
            return response()->json($result);
        }

        return redirect()->route('subscription.payment', $payment)
            ->with($result['verified'] ? 'success' : 'error', $result['message']);
    }

    public function paymentStatus(Payment $payment)
    {
        abort_unless($payment->user_id === Auth::id(), 403);
        $payment->load('subscription.plan');

        return response()->json([
            'status' => $payment->status,
            'verified' => $payment->status === 'paid',
            'paid_at' => optional($payment->paid_at)?->toIso8601String(),
            'email_verified_at' => optional($payment->email_verified_at)?->toIso8601String(),
            'bank_transaction_ref' => $payment->bank_transaction_ref,
        ]);
    }

    public function uploadProof(Request $request, Payment $payment)
    {
        abort_unless($payment->user_id === Auth::id(), 403);

        $data = $request->validate([
            'proof_image' => ['required', 'image', 'max:4096'],
            'payer_name' => ['required', 'string', 'max:255'],
            'payer_bank' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $path = $request->file('proof_image')->store('payment-proofs', 'public');

        $payment->update([
            'proof_image' => $path,
            'payer_name' => $data['payer_name'],
            'payer_bank' => $data['payer_bank'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        // Transfer BSI: tunggu validasi email otomatis; metode lain / demo tetap manual
        if ($payment->method !== 'transfer') {
            $this->paymentService->activate($payment->subscription, $payment, $payment->subscription->plan);

            return redirect()->route('subscription.index')
                ->with('success', 'Bukti pembayaran diterima. Langganan telah diaktifkan.');
        }

        return redirect()->route('subscription.payment', $payment)
            ->with('success', 'Bukti transfer disimpan. Sistem akan cek email BSI secara otomatis.');
    }

    public function confirmDemo(Payment $payment)
    {
        abort_unless($payment->user_id === Auth::id(), 403);

        if ($payment->status !== 'paid') {
            $this->paymentService->activate(
                $payment->subscription,
                $payment,
                $payment->subscription->plan
            );
        }

        return redirect()->route('subscription.index')
            ->with('success', 'Pembayaran berhasil dikonfirmasi.');
    }
}
