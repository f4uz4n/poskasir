<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = static::allCached();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) ($value ?? '')]
        );

        Cache::forget('app_settings');
    }

    public static function setMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            static::updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) ($value ?? '')]
            );
        }

        Cache::forget('app_settings');
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    /** Mode online = captcha bisa aktif; offline = tanpa captcha. */
    public static function isOnlineMode(): bool
    {
        return static::get('deployment_mode', 'offline') === 'online';
    }

    public static function recaptchaEnabled(): bool
    {
        return static::isOnlineMode()
            && static::bool('recaptcha_enabled')
            && filled(static::get('recaptcha_site_key'))
            && filled(static::get('recaptcha_secret_key'));
    }

    protected static function allCached(): array
    {
        return Cache::remember('app_settings', 60, function () {
            return static::query()->pluck('value', 'key')->all();
        });
    }
}
