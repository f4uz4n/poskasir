<?php

namespace App\Console\Commands;

use App\Services\SubscriptionEmailVerifier;
use Illuminate\Console\Command;

class VerifySubscriptionPayments extends Command
{
    protected $signature = 'subscription:verify-payments {--debug : Tampilkan detail email & nominal yang ditemukan}';

    protected $description = 'Cek email BSI masuk dan validasi pembayaran langganan otomatis';

    public function handle(SubscriptionEmailVerifier $verifier): int
    {
        $this->info('Memeriksa email notifikasi BSI...');

        if ($this->option('debug')) {
            $passLen = strlen((string) config('subscription.email.password'));
            $this->line('IMAP user: '.(config('subscription.email.username') ?: '(kosong)'));
            $this->line('IMAP pass: '.(filled(config('subscription.email.password')) ? 'terisi ('.$passLen.' karakter, harus 16)' : '(kosong)'));
            $this->line('Bank account: '.(config('subscription.bank.account') ?: '(kosong)'));
            $this->line('PHP imap: '.(extension_loaded('imap') ? 'aktif' : 'TIDAK AKTIF'));
            if ($passLen !== 16) {
                $this->warn('App Password Gmail harus 16 karakter. Di .env pakai tanda kutip, contoh: SUBSCRIPTION_IMAP_PASSWORD="xxxx xxxx xxxx xxxx"');
            }
        }

        $result = $verifier->verify();

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        $this->info("Email dicek: {$result['checked']}, cocok: {$result['matched']}");

        if ($this->option('debug')) {
            $logs = \App\Models\PaymentEmailLog::query()->latest()->limit(5)->get(['id', 'status', 'amount', 'bank_transaction_ref', 'raw_snippet', 'created_at']);
            if ($logs->isEmpty()) {
                $this->warn('Belum ada log email di payment_email_logs.');
            } else {
                $this->table(
                    ['id', 'status', 'amount', 'ref', 'snippet', 'at'],
                    $logs->map(fn ($l) => [
                        $l->id,
                        $l->status,
                        $l->amount,
                        $l->bank_transaction_ref,
                        mb_substr((string) $l->raw_snippet, 0, 80),
                        optional($l->created_at)->format('Y-m-d H:i'),
                    ])->all()
                );
            }
        }

        return empty($result['errors']) ? self::SUCCESS : self::FAILURE;
    }
}
