<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoginDeviceService
{
    /** 0 = tanpa batas perangkat login. */
    public function maxDevicesFor(User $user): int
    {
        if ($user->isDeveloper()) {
            return 0;
        }

        $owner = $user->storeOwner();
        $max = (int) ($owner->storeSetting?->max_login_devices ?? 1);

        return max(0, min($max, 50));
    }

    /**
     * Terapkan batas perangkat setelah login berhasil.
     * max 1 → keluarkan semua sesi lain (perangkat lama harus login ulang).
     */
    public function enforceAfterLogin(User $user, string $password, string $currentSessionId): void
    {
        $max = $this->maxDevicesFor($user);

        if ($max === 1) {
            Auth::logoutOtherDevices($password);

            return;
        }

        if ($max === 0) {
            return;
        }

        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'last_activity']);

        if ($sessions->count() <= $max) {
            return;
        }

        $keepIds = $sessions
            ->take($max)
            ->pluck('id')
            ->all();

        // Pastikan sesi saat ini tetap ada
        if (! in_array($currentSessionId, $keepIds, true)) {
            $keepIds = array_slice([$currentSessionId, ...array_filter($keepIds, fn ($id) => $id !== $currentSessionId)], 0, $max);
        }

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /** @return array<int, array{id: string, ip: ?string, agent: ?string, last_activity: int}> */
    public function activeSessionsFor(User $user, int $limit = 20): array
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->limit($limit)
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'ip' => $row->ip_address,
                'agent' => $row->user_agent,
                'last_activity' => (int) $row->last_activity,
            ])
            ->all();
    }

    public function countActiveSessions(User $user): int
    {
        return (int) DB::table('sessions')->where('user_id', $user->id)->count();
    }
}
