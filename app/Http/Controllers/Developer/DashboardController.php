<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all'); // all|paid|free|none
        $q = $request->get('q');

        $ownersQuery = User::query()
            ->where('role', 'owner')
            ->whereNull('owner_id')
            ->with(['subscriptions' => function ($query) {
                $query->with('plan')
                    ->where('status', 'active')
                    ->where(function ($inner) {
                        $inner->whereNull('ends_at')->orWhere('ends_at', '>', now());
                    })
                    ->latest();
            }])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('store_name', 'like', "%{$q}%");
                });
            });

        $owners = $ownersQuery->latest()->get()->map(function (User $owner) {
            $active = $owner->subscriptions->first();
            $plan = $active?->plan;
            $status = 'none';
            if ($plan) {
                $status = (! $plan->is_free && (float) $plan->price > 0) ? 'paid' : 'free';
            }

            return [
                'user' => $owner,
                'subscription' => $active,
                'plan' => $plan,
                'status' => $status,
                'cashiers' => $owner->cashiers()->count(),
            ];
        });

        if ($filter !== 'all') {
            $owners = $owners->where('status', $filter)->values();
        }

        $summary = [
            'owners' => User::where('role', 'owner')->whereNull('owner_id')->count(),
            'paid' => 0,
            'free' => 0,
            'none' => 0,
            'pending_payments' => Payment::where('status', 'pending')->count(),
            'revenue_paid' => Payment::where('status', 'paid')->sum('amount'),
        ];

        User::where('role', 'owner')->whereNull('owner_id')->with(['subscriptions' => function ($query) {
            $query->with('plan')
                ->where('status', 'active')
                ->where(function ($inner) {
                    $inner->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->latest();
        }])->get()->each(function (User $owner) use (&$summary) {
            $plan = $owner->subscriptions->first()?->plan;
            if (! $plan) {
                $summary['none']++;
            } elseif (! $plan->is_free && (float) $plan->price > 0) {
                $summary['paid']++;
            } else {
                $summary['free']++;
            }
        });

        $recentPayments = Payment::with(['user', 'subscription.plan'])
            ->latest()
            ->limit(10)
            ->get();

        return view('developer.dashboard', compact('owners', 'summary', 'filter', 'q', 'recentPayments'));
    }

    public function toggleUser(User $user)
    {
        abort_unless($user->role === 'owner' && blank($user->owner_id), 404);
        abort_if($user->isDeveloper(), 403);

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }

    public function assignPlan(Request $request, User $user)
    {
        abort_unless($user->role === 'owner' && blank($user->owner_id), 404);

        $data = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'extend_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);

        $plan = SubscriptionPlan::findOrFail($data['plan_id']);

        DB::transaction(function () use ($user, $plan, $data) {
            $user->subscriptions()->where('status', 'active')->update(['status' => 'expired']);

            $endsAt = null;
            if (! $plan->is_free) {
                $days = $data['extend_days'] ?? $plan->duration_days;
                $endsAt = now()->addDays(max(1, (int) $days));
            }

            Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => $endsAt,
            ]);
        });

        return back()->with('success', "Paket {$plan->name} ditetapkan untuk {$user->store_name}.");
    }
}
