<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\SubscriptionPaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private SubscriptionPaymentService $payments,
    ) {}

    public function index(Request $request)
    {
        $filter = $request->get('filter', 'awaiting');

        $query = Payment::query()
            ->with(['user', 'subscription.plan', 'manualVerifier'])
            ->where('method', 'transfer')
            ->latest();

        $query = match ($filter) {
            'awaiting' => $query->awaitingManualReview(),
            'pending' => $query->where('status', 'pending'),
            'paid' => $query->where('status', 'paid'),
            'failed' => $query->where('status', 'failed'),
            default => $query,
        };

        $payments = $query->paginate(15)->withQueryString();

        $counts = [
            'awaiting' => Payment::awaitingManualReview()->count(),
            'pending' => Payment::where('method', 'transfer')->where('status', 'pending')->count(),
        ];

        return view('developer.payments.index', compact('payments', 'filter', 'counts'));
    }

    public function approve(Payment $payment)
    {
        abort_unless($payment->method === 'transfer' && $payment->status === 'pending', 404);

        if (! filled($payment->proof_image)) {
            return back()->with('error', 'Belum ada bukti transfer.');
        }

        $this->payments->approveManual($payment, auth()->user());

        return back()->with('success', "Pembayaran {$payment->invoice_code} disetujui. Langganan toko aktif.");
    }

    public function reject(Request $request, Payment $payment)
    {
        abort_unless($payment->method === 'transfer' && $payment->status === 'pending', 404);

        $data = $request->validate([
            'admin_notes' => ['required', 'string', 'max:500'],
        ]);

        $this->payments->rejectManual($payment, auth()->user(), $data['admin_notes']);

        return back()->with('success', "Pembayaran {$payment->invoice_code} ditolak.");
    }
}
