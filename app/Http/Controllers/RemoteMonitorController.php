<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RemoteMonitorController extends Controller
{
    public function show(string $token)
    {
        $settings = StoreSetting::where('remote_monitor_token', $token)
            ->where('remote_monitor_enabled', true)
            ->firstOrFail();

        $owner = $settings->user;
        abort_unless($owner && $owner->hasFeature('remote_laporan'), 403);

        $today = now()->toDateString();

        $summary = [
            'store_name' => $settings->store_name ?: $owner->store_name,
            'today_sales' => Transaction::where('user_id', $owner->id)
                ->where('status', 'completed')
                ->whereDate('sold_at', $today)
                ->sum('total'),
            'today_count' => Transaction::where('user_id', $owner->id)
                ->where('status', 'completed')
                ->whereDate('sold_at', $today)
                ->count(),
            'month_sales' => Transaction::where('user_id', $owner->id)
                ->where('status', 'completed')
                ->whereMonth('sold_at', now()->month)
                ->whereYear('sold_at', now()->year)
                ->sum('total'),
        ];

        $hpp = TransactionItem::query()
            ->whereHas('transaction', function ($q) use ($owner, $today) {
                $q->where('user_id', $owner->id)
                    ->where('status', 'completed')
                    ->whereDate('sold_at', $today);
            })
            ->selectRaw('COALESCE(SUM(cost * qty), 0) as total_hpp')
            ->value('total_hpp');

        $summary['today_hpp'] = (float) $hpp;
        $summary['today_profit'] = (float) $summary['today_sales'] - (float) $hpp;

        $recent = Transaction::where('user_id', $owner->id)
            ->with('cashier')
            ->where('status', 'completed')
            ->latest('sold_at')
            ->limit(15)
            ->get();

        $hourly = Transaction::where('user_id', $owner->id)
            ->where('status', 'completed')
            ->whereDate('sold_at', $today)
            ->select(DB::raw("strftime('%H', sold_at) as hour"), DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as trx'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // Fallback MySQL-compatible hour extract if not sqlite
        if (config('database.default') === 'mysql') {
            $hourly = Transaction::where('user_id', $owner->id)
                ->where('status', 'completed')
                ->whereDate('sold_at', $today)
                ->select(DB::raw('HOUR(sold_at) as hour'), DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as trx'))
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
        }

        return view('remote.monitor', compact('settings', 'summary', 'recent', 'hourly', 'token'));
    }

    public function enable(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isStoreOwner(), 403);

        if (! $user->hasFeature('remote_laporan')) {
            return back()->with('error', 'Pantau laporan jarak jauh hanya untuk paket berbayar.');
        }

        $settings = $user->storeSetting;
        if (! $settings) {
            $settings = $user->storeSetting()->create(['user_id' => $user->id, 'store_name' => $user->store_name]);
        }

        $settings->update([
            'remote_monitor_enabled' => true,
            'remote_monitor_token' => $settings->remote_monitor_token ?: Str::random(48),
        ]);

        return back()->with('success', 'Pemantauan jarak jauh diaktifkan.');
    }

    public function regenerate(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isStoreOwner() && $user->hasFeature('remote_laporan'), 403);

        $settings = $user->storeSetting;
        abort_unless($settings, 404);

        $settings->update([
            'remote_monitor_enabled' => true,
            'remote_monitor_token' => Str::random(48),
        ]);

        return back()->with('success', 'Link pantau jarak jauh diperbarui.');
    }

    public function disable(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isStoreOwner(), 403);

        $user->storeSetting?->update(['remote_monitor_enabled' => false]);

        return back()->with('success', 'Pemantauan jarak jauh dinonaktifkan.');
    }
}
