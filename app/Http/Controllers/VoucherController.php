<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $q = trim((string) $request->get('q', ''));
        $status = $request->get('status') ?: null;

        // Tandai kadaluarsa otomatis
        Voucher::where('user_id', $ownerId)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $vouchers = Voucher::where('user_id', $ownerId)
            ->with(['creator:id,name', 'usedOnTransaction:id,invoice_number'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('code', 'like', "%{$q}%")
                        ->orWhere('title', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%");
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->onEachSide(1)
            ->withQueryString();

        $summary = [
            'active' => Voucher::where('user_id', $ownerId)->where('status', 'active')->count(),
            'used' => Voucher::where('user_id', $ownerId)->where('status', 'used')->count(),
            'active_value' => (float) Voucher::where('user_id', $ownerId)->where('status', 'active')->sum('amount'),
        ];

        return view('vouchers.index', compact('vouchers', 'q', 'status', 'summary'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:1'],
            'qty' => ['required', 'integer', 'min:1', 'max:200'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $actor = Auth::user();
        $ownerId = $actor->storeOwnerId();
        $created = [];

        DB::transaction(function () use ($data, $actor, $ownerId, &$created) {
            for ($i = 0; $i < (int) $data['qty']; $i++) {
                $created[] = Voucher::create([
                    'user_id' => $ownerId,
                    'created_by' => $actor->id,
                    'code' => $this->generateUniqueCode(),
                    'title' => $data['title'] ?: 'Voucher Belanja',
                    'amount' => $data['amount'],
                    'status' => 'active',
                    'expires_at' => $data['expires_at'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);
            }
        });

        $ids = collect($created)->pluck('id')->all();

        return redirect()
            ->route('vouchers.print', ['ids' => implode(',', $ids)])
            ->with('success', count($created).' voucher berhasil dibuat. Siap dicetak.');
    }

    public function print(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $ids = collect(explode(',', (string) $request->get('ids', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values();

        abort_if($ids->isEmpty(), 404);

        $vouchers = Voucher::where('user_id', $ownerId)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        abort_if($vouchers->isEmpty(), 404);

        $storeName = Auth::user()->storeOwner()->store_name
            ?? Auth::user()->storeOwner()->storeSetting?->store_name
            ?? 'Toko';

        return view('vouchers.print', compact('vouchers', 'storeName'));
    }

    public function cancel(Voucher $voucher)
    {
        return $this->destroy($voucher);
    }

    public function destroy(Voucher $voucher)
    {
        abort_unless($voucher->user_id === Auth::user()->storeOwnerId(), 403);

        if ($voucher->status === 'used') {
            return back()->withErrors(['voucher' => 'Voucher sudah terpakai tidak bisa dihapus.']);
        }

        // Lepas referensi di transaksi (jika ada) lalu hapus record
        \App\Models\Transaction::where('voucher_id', $voucher->id)->update([
            'voucher_id' => null,
        ]);
        \App\Models\TransactionPayment::where('voucher_id', $voucher->id)->update([
            'voucher_id' => null,
        ]);

        $code = $voucher->code;
        $voucher->delete();

        return back()->with('success', 'Voucher '.$code.' dihapus.');
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'total' => ['nullable', 'numeric', 'min:0'],
        ]);

        $ownerId = Auth::user()->storeOwnerId();
        $code = strtoupper(trim($data['code']));

        $voucher = Voucher::where('user_id', $ownerId)
            ->where('code', $code)
            ->first();

        if (! $voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher tidak ditemukan.',
            ], 404);
        }

        $voucher->markExpiredIfNeeded();
        $voucher->refresh();

        if (! $voucher->isUsable()) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher tidak dapat dipakai ('.$voucher->statusLabel().').',
                'voucher' => [
                    'code' => $voucher->code,
                    'status' => $voucher->status,
                    'status_label' => $voucher->statusLabel(),
                    'amount' => (float) $voucher->amount,
                ],
            ], 422);
        }

        $total = (float) ($data['total'] ?? 0);
        $amount = (float) $voucher->amount;

        return response()->json([
            'success' => true,
            'message' => 'Voucher valid.',
            'voucher' => [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'title' => $voucher->title,
                'amount' => $amount,
                'expires_at' => optional($voucher->expires_at)->toIso8601String(),
                'covers_total' => $total <= 0 || $amount + 0.0001 >= $total,
                'shortfall' => $total > 0 ? max(0, round($total - $amount, 2)) : 0,
            ],
        ]);
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = 'VCH'.now()->format('ymd').Str::upper(Str::random(6));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }
}
