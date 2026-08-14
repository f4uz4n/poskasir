<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->orderBy('id')->get();

        return view('developer.plans', compact('plans'));
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_free' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'features_text' => ['nullable', 'string'],
        ]);

        $features = collect(preg_split('/\r\n|\r|\n/', $data['features_text'] ?? ''))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        $plan->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'duration_days' => $data['duration_days'],
            'is_active' => $request->boolean('is_active'),
            'is_free' => $request->boolean('is_free') || (float) $data['price'] === 0.0,
            'sort_order' => $data['sort_order'] ?? $plan->sort_order,
            'features' => $features,
        ]);

        return back()->with('success', "Paket {$plan->name} diperbarui.");
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:subscription_plans,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:0'],
            'features_text' => ['nullable', 'string'],
        ]);

        $features = collect(preg_split('/\r\n|\r|\n/', $data['features_text'] ?? ''))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        $slug = $data['slug'] ?: Str::slug($data['name']);
        $slug = $slug ?: 'plan-'.Str::random(6);

        SubscriptionPlan::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'duration_days' => $data['duration_days'],
            'is_active' => true,
            'is_free' => (float) $data['price'] === 0.0,
            'features' => $features,
            'feature_flags' => [
                'multi_kasir' => (float) $data['price'] > 0,
                'remote_laporan' => (float) $data['price'] > 0,
                'kunci_stok' => (float) $data['price'] > 0,
            ],
            'sort_order' => (SubscriptionPlan::max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('success', 'Paket baru ditambahkan.');
    }
}
