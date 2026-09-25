<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutoMigrateService
{
    /**
     * Jalankan migrasi pending otomatis saat ada file migrasi baru.
     * Aman untuk concurrent request (file lock) dan hampir no-op jika sudah up-to-date.
     */
    public function runIfNeeded(): void
    {
        if (! config('app.auto_migrate', true)) {
            return;
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            // Jangan ganggu perintah artisan migrate / seeder manual
            $argv = $_SERVER['argv'] ?? [];
            $command = implode(' ', $argv);
            if (str_contains($command, 'migrate')) {
                return;
            }
        }

        $stampPath = storage_path('framework/auto-migrate.stamp');
        $lockPath = storage_path('framework/auto-migrate.lock');
        $fingerprint = $this->migrationsFingerprint();

        if (is_file($stampPath) && trim((string) @file_get_contents($stampPath)) === $fingerprint) {
            return;
        }

        if (! is_dir(dirname($lockPath))) {
            @mkdir(dirname($lockPath), 0775, true);
        }

        $lock = @fopen($lockPath, 'c+');
        if ($lock === false) {
            return;
        }

        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                return;
            }

            // Cek ulang setelah dapat lock
            if (is_file($stampPath) && trim((string) @file_get_contents($stampPath)) === $fingerprint) {
                return;
            }

            Artisan::call('migrate', ['--force' => true]);

            @file_put_contents($stampPath, $fingerprint);
        } catch (Throwable $e) {
            Log::error('Auto-migrate gagal: '.$e->getMessage(), [
                'exception' => $e,
            ]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function migrationsFingerprint(): string
    {
        $dir = database_path('migrations');
        if (! is_dir($dir)) {
            return 'none';
        }

        $files = glob($dir.DIRECTORY_SEPARATOR.'*.php') ?: [];
        sort($files);

        $parts = [];
        foreach ($files as $file) {
            $parts[] = basename($file).':'.(string) @filemtime($file);
        }

        return hash('sha256', implode('|', $parts));
    }
}
