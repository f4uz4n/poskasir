<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = [
            'deployment_mode' => AppSetting::get('deployment_mode', 'offline'),
            'recaptcha_enabled' => AppSetting::bool('recaptcha_enabled'),
            'recaptcha_site_key' => AppSetting::get('recaptcha_site_key', ''),
            'recaptcha_secret_key' => AppSetting::get('recaptcha_secret_key', ''),
        ];

        return view('developer.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'deployment_mode' => ['required', 'in:online,offline'],
            'recaptcha_enabled' => ['nullable', 'boolean'],
            'recaptcha_site_key' => ['nullable', 'string', 'max:255'],
            'recaptcha_secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        $enabled = $request->boolean('recaptcha_enabled');

        if ($data['deployment_mode'] === 'online' && $enabled) {
            $request->validate([
                'recaptcha_site_key' => ['required', 'string', 'max:255'],
                'recaptcha_secret_key' => ['required', 'string', 'max:255'],
            ]);
        }

        AppSetting::setMany([
            'deployment_mode' => $data['deployment_mode'],
            'recaptcha_enabled' => $enabled,
            'recaptcha_site_key' => $data['recaptcha_site_key'] ?? '',
            'recaptcha_secret_key' => $data['recaptcha_secret_key'] ?? '',
        ]);

        $msg = $data['deployment_mode'] === 'offline'
            ? 'Mode offline aktif — Google reCAPTCHA tidak ditampilkan.'
            : ($enabled
                ? 'Mode online + reCAPTCHA aktif untuk login, daftar, dan langganan.'
                : 'Mode online aktif, reCAPTCHA dimatikan.');

        return back()->with('success', $msg);
    }
}
