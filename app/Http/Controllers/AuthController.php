<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin(RecaptchaService $recaptcha)
    {
        return view('auth.login', [
            'recaptchaEnabled' => $recaptcha->shouldChallenge(request()),
        ]);
    }

    public function login(Request $request, RecaptchaService $recaptcha)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $recaptcha->verifyOrFail($request);

        // Selalu "ingat perangkat ini" agar kasir tidak minta login ulang di device yang sama
        if (Auth::attempt($credentials, true)) {
            $user = Auth::user();

            if (! $user->is_active) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Akun dinonaktifkan. Hubungi pemilik toko.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            // Login di perangkat lain → sesi perangkat lama keluar (harus login lagi)
            Auth::logoutOtherDevices($credentials['password']);

            if ($user->isDeveloper()) {
                return redirect()->intended(route('developer.dashboard'));
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->onlyInput('email');
    }

    public function showRegister(RecaptchaService $recaptcha)
    {
        return view('auth.register', [
            'recaptchaEnabled' => $recaptcha->shouldChallenge(request()),
        ]);
    }

    public function register(Request $request, RecaptchaService $recaptcha)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'store_name' => ['required', 'string', 'max:255'],
            'store_address' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $recaptcha->verifyOrFail($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'store_name' => $data['store_name'],
            'store_address' => $data['store_address'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'owner',
        ]);

        StoreSetting::create([
            'user_id' => $user->id,
            'store_name' => $data['store_name'],
            'store_phone' => $data['phone'] ?? null,
            'store_address' => $data['store_address'] ?? null,
            'receipt_header' => $data['store_name'],
            'receipt_footer' => 'Terima kasih telah berbelanja',
        ]);

        $free = SubscriptionPlan::where('slug', 'gratis')->first()
            ?? SubscriptionPlan::where('is_free', true)->first();

        if ($free) {
            Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $free->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => null,
            ]);
        }

        Auth::login($user, true);

        return redirect()->route('dashboard')->with('success', 'Akun berhasil dibuat. Anda memakai paket Gratis (1 akun).');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
