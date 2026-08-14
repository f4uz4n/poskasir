<?php

namespace App\Http\Controllers;

use App\Models\FinancePayment;
use App\Models\Receivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReceivableController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = Auth::user()->storeOwnerId();
        $status = $request->get('status');

        $summary = [
            'total' => Receivable::where('user_id', $ownerId)->sum('amount'),
            'paid' => Receivable::where('user_id', $ownerId)->sum('paid_amount'),
            'outstanding' => Receivable::where('user_id', $ownerId)
                ->whereIn('status', ['unpaid', 'partial'])
                ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as v')
                ->value('v'),
            'count_open' => Receivable::where('user_id', $ownerId)->whereIn('status', ['unpaid', 'partial'])->count(),
        ];

        $items = Receivable::where('user_id', $ownerId)
            ->with('transaction')
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

        return view('receivables.index', compact('items', 'summary', 'status'));
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

        Receivable::create([
            'user_id' => $actor->storeOwnerId(),
            'created_by' => $actor->id,
            'code' => 'PT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'party_name' => $data['party_name'],
            'source' => 'manual',
            'amount' => $data['amount'],
            'paid_amount' => 0,
            'due_date' => $data['due_date'] ?? null,
            'status' => 'unpaid',
            'notes' => $data['notes'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);

        return back()->with('success', 'Piutang ditambahkan.');
    }

    public function pay(Request $request, Receivable $receivable)
    {
        abort_unless($receivable->user_id === Auth::user()->storeOwnerId(), 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,transfer,qris,other'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $remaining = $receivable->remaining();
        if ($remaining <= 0) {
            return back()->with('error', 'Piutang sudah lunas.');
        }

        $amount = min((float) $data['amount'], $remaining);
        $actor = Auth::user();

        DB::transaction(function () use ($receivable, $amount, $data, $actor) {
            FinancePayment::create([
                'user_id' => $actor->storeOwnerId(),
                'created_by' => $actor->id,
                'payable_type' => 'receivable',
                'payable_id' => $receivable->id,
                'amount' => $amount,
                'method' => $data['method'],
                'notes' => $data['notes'] ?? null,
                'paid_at' => now(),
            ]);

            $receivable->paid_amount = (float) $receivable->paid_amount + $amount;
            $receivable->refreshStatus();
        });

        return back()->with('success', 'Pembayaran piutang dicatat.');
    }
}
