<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ApiTokenController extends Controller
{
    public function generate(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->isStoreOwner(), 403);
        abort_unless($user->hasFeature('api_sync'), 403, 'Fitur API sinkron hanya tersedia pada paket Berbayar.');

        $settings = $user->storeSetting;
        if (! $settings) {
            $settings = $user->storeSetting()->create([
                'user_id' => $user->id,
                'store_name' => $user->store_name,
            ]);
        }

        $plain = Str::random(64);
        $settings->update([
            'api_token' => hash('sha256', $plain),
            'api_token_created_at' => now(),
        ]);

        return back()
            ->with('success', 'Token API berhasil dibuat. Salin token sekarang — tidak ditampilkan lagi.')
            ->with('api_token_plain', $plain);
    }

    public function revoke()
    {
        $user = Auth::user();
        abort_unless($user->isStoreOwner(), 403);

        $settings = $user->storeSetting;
        if ($settings) {
            $settings->update([
                'api_token' => null,
                'api_token_created_at' => null,
            ]);
        }

        return back()->with('success', 'Token API telah dicabut.');
    }
}
