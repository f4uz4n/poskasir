<?php

namespace App\Console\Commands;

use App\Services\SubscriptionEmailVerifier;
use Illuminate\Console\Command;

class VerifySubscriptionPayments extends Command
{
    protected $signature = 'subscription:verify-payments';

    protected $description = 'Cek email BSI masuk dan validasi pembayaran langganan otomatis';

    public function handle(SubscriptionEmailVerifier $verifier): int
    {
        $this->info('Memeriksa email notifikasi BSI...');

        $result = $verifier->verify();

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        $this->info("Email dicek: {$result['checked']}, cocok: {$result['matched']}");

        return empty($result['errors']) ? self::SUCCESS : self::FAILURE;
    }
}
