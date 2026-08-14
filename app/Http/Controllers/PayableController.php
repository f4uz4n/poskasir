<?php

namespace App\Http\Controllers;

use App\Models\FinancePayment;
use App\Models\Payable;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayableController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $status = $request->get('status');

        $summary = [
            'total' => Payable::where('user_id', $ownerId)->sum('amount'),
            'paid' => Payable::where('user_id', $ownerId)->sum('paid_amount'),
            'outstanding' => Payable::where('user_id', $ownerId)
                ->whereIn('status', ['unpaid', 'partial'])
                ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as v')
                ->value('v'),
            'count_open' => Payable::where('user_id', $ownerId)->whereIn('status', ['unpaid', 'partial'])->count(),
        ];

        $items = Payable::where('user_id', $ownerId)
            ->with('purchase')
            ->when($request->get('q'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('party_name', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('recorded_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('payables.index', compact('items', 'summary', 'status'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'party_name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $actor = Auth::user();

        Payable::create([
            'user_id' => $actor->storeOwnerId(),
            'created_by' => $actor->id,
            'code' => 'HT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'party_name' => $data['party_name'],
            'source' => 'manual',
            'amount' => $data['amount'],
            'paid_amount' => 0,
            'due_date' => $data['due_date'] ?? null,
            'status' => 'unpaid',
            'notes' => $data['notes'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);

        return back()->with('success', 'Hutang ditambahkan.');
    }

    public function pay(Request $request, Payable $payable)
    {
        abort_unless($payable->user_id === Auth::user()->storeOwnerId(), 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,transfer,qris,other'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $remaining = $payable->remaining();
        if ($remaining <= 0) {
            return back()->with('error', 'Hutang sudah lunas.');
        }

        $amount = min((float) $data['amount'], $remaining);
        $actor = Auth::user();

        DB::transaction(function () use ($payable, $amount, $data, $actor) {
            FinancePayment::create([
                'user_id' => $actor->storeOwnerId(),
                'created_by' => $actor->id,
                'payable_type' => 'payable',
                'payable_id' => $payable->id,
                'amount' => $amount,
                'method' => $data['method'],
                'notes' => $data['notes'] ?? null,
                'paid_at' => now(),
            ]);

            $payable->paid_amount = (float) $payable->paid_amount + $amount;
            $payable->refreshStatus();

            if ($payable->purchase_id) {
                $purchase = Purchase::find($payable->purchase_id);
                if ($purchase) {
                    $purchase->paid = min((float) $purchase->total, (float) $purchase->paid + $amount);
                    if ((float) $purchase->paid + 0.0001 >= (float) $purchase->total) {
                        $purchase->payment_status = 'paid';
                    } elseif ((float) $purchase->paid > 0) {
                        $purchase->payment_status = 'partial';
                    }
                    $purchase->save();
                }
            }
        });

        return back()->with('success', 'Pembayaran hutang dicatat.');
    }
}
