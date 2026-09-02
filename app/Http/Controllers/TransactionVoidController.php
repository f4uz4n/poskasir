<?php

namespace App\Http\Controllers;

use App\Services\TransactionVoidService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class TransactionVoidController extends Controller
{
    public function __construct(
        protected TransactionVoidService $voidService
    ) {}

    public function create(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->canAccessArea('void'), 403);

        $invoice = trim((string) $request->get('invoice', ''));
        $preview = null;

        if ($invoice !== '') {
            $preview = $this->voidService->findByInvoice($invoice, $user->storeOwnerId());
        }

        return view('transactions.void', compact('invoice', 'preview'));
    }

    public function lookup(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->canAccessArea('void'), 403);

        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:100'],
        ]);

        $transaction = $this->voidService->findByInvoice($data['invoice_number'], $user->storeOwnerId());

        if (! $transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor struk tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'transaction' => [
                'id' => $transaction->id,
                'invoice_number' => $transaction->invoice_number,
                'status' => $transaction->status,
                'total' => (float) $transaction->total,
                'sold_at' => optional($transaction->sold_at)->toIso8601String(),
                'customer_name' => $transaction->customer_name,
                'cashier' => $transaction->cashier?->name,
                'items_count' => $transaction->items->sum('qty'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->canAccessArea('void'), 403);

        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:100'],
            'owner_password' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if (! $this->voidService->verifyOwnerPassword($user, $data['owner_password'])) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password pimpinan toko salah. Pembatalan ditolak.',
                ], 422);
            }

            return back()
                ->withInput($request->except('owner_password'))
                ->with('error', 'Password pimpinan toko salah. Pembatalan ditolak.');
        }

        $transaction = $this->voidService->findByInvoice($data['invoice_number'], $user->storeOwnerId());

        if (! $transaction) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor struk tidak ditemukan.',
                ], 404);
            }

            return back()
                ->withInput($request->except('owner_password'))
                ->with('error', 'Nomor struk tidak ditemukan.');
        }

        try {
            $this->voidService->void($transaction, $user, $data['reason']);
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()
                ->withInput($request->except('owner_password'))
                ->with('error', $e->getMessage());
        }

        $message = 'Transaksi '.$transaction->invoice_number.' berhasil dibatalkan. Stok telah dikembalikan.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'transaction' => $transaction->fresh(['items']),
            ]);
        }

        return redirect()
            ->route('transactions.void.create')
            ->with('success', $message);
    }
}
