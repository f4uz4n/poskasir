<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentEmailLog;
use Illuminate\Support\Facades\Log;

class SubscriptionEmailVerifier
{
    public function __construct(
        private BsiEmailParser $parser,
        private SubscriptionPaymentService $payments,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('subscription.email.username'))
            && filled(config('subscription.email.password'));
    }

    public function extensionAvailable(): bool
    {
        return extension_loaded('imap');
    }

    /** @return array{checked:int,matched:int,errors:array<int,string>} */
    public function verify(?Payment $onlyPayment = null): array
    {
        $result = ['checked' => 0, 'matched' => 0, 'errors' => []];

        if (! $this->extensionAvailable()) {
            $result['errors'][] = 'Ekstensi PHP IMAP belum aktif. Aktifkan php_imap di php.ini.';

            return $result;
        }

        if (! $this->isConfigured()) {
            $result['errors'][] = 'Email IMAP belum dikonfigurasi di .env (SUBSCRIPTION_IMAP_*).';

            return $result;
        }

        $this->payments->expireOldPendingPayments();

        $connection = $this->connect();
        if (! $connection) {
            $result['errors'][] = 'Gagal konek ke mailbox: '.(imap_last_error() ?: 'unknown');

            return $result;
        }

        try {
            $messageNumbers = $this->searchMessages($connection);
            rsort($messageNumbers);

            foreach ($messageNumbers as $msgNum) {
                $uid = imap_uid($connection, $msgNum);
                if (! $uid) {
                    continue;
                }

                $uidKey = (string) $uid;
                if (PaymentEmailLog::where('message_uid', $uidKey)->exists()) {
                    continue;
                }

                $body = $this->fetchBody($connection, $msgNum);
                $parsed = $this->parser->parse($body);

                $result['checked']++;

                if (! $parsed) {
                    PaymentEmailLog::create([
                        'message_uid' => $uidKey,
                        'status' => 'skipped',
                        'raw_snippet' => mb_substr($body, 0, 300),
                    ]);
                    continue;
                }

                $matchResult = $this->matchAndActivate($parsed, $onlyPayment);

                PaymentEmailLog::create([
                    'message_uid' => $uidKey,
                    'bank_transaction_ref' => $parsed['bank_transaction_ref'],
                    'amount' => $parsed['amount'],
                    'payment_id' => $matchResult['payment_id'],
                    'status' => $matchResult['status'],
                    'raw_snippet' => $parsed['snippet'],
                ]);

                if ($matchResult['status'] === 'matched') {
                    $result['matched']++;
                }
            }
        } finally {
            imap_close($connection);
        }

        return $result;
    }

    /** @return array{verified:bool,message:string,payment?:Payment} */
    public function verifySingle(Payment $payment): array
    {
        if ($payment->status === 'paid') {
            return ['verified' => true, 'message' => 'Pembayaran sudah tervalidasi.', 'payment' => $payment->fresh()];
        }

        if ($payment->status !== 'pending') {
            return ['verified' => false, 'message' => 'Pembayaran tidak dalam status menunggu.'];
        }

        if ($payment->expires_at && $payment->expires_at->isPast()) {
            $payment->update(['status' => 'expired']);

            return ['verified' => false, 'message' => 'Batas waktu pembayaran telah habis.'];
        }

        $result = $this->verify($payment);

        $payment->refresh();

        if ($payment->status === 'paid') {
            return ['verified' => true, 'message' => 'Pembayaran tervalidasi otomatis dari email BSI.', 'payment' => $payment];
        }

        $error = $result['errors'][0] ?? null;

        return [
            'verified' => false,
            'message' => $error ?: 'Belum ditemukan transfer dengan nominal Rp '.number_format($payment->expected_amount ?? $payment->amount, 0, ',', '.').'. Pastikan transfer sesuai nominal + kode unit.',
            'payment' => $payment,
        ];
    }

    /** @param array<string,mixed> $parsed */
    protected function matchAndActivate(array $parsed, ?Payment $onlyPayment = null): array
    {
        $ref = $parsed['bank_transaction_ref'] ?? null;
        $amount = (float) $parsed['amount'];

        if ($ref && Payment::where('bank_transaction_ref', $ref)->exists()) {
            return ['status' => 'duplicate', 'payment_id' => null];
        }

        if ($ref && PaymentEmailLog::where('bank_transaction_ref', $ref)->where('status', 'matched')->exists()) {
            return ['status' => 'duplicate', 'payment_id' => null];
        }

        $query = Payment::query()
            ->with('subscription.plan')
            ->where('status', 'pending')
            ->where('method', 'transfer')
            ->where(function ($q) use ($amount) {
                $q->where('expected_amount', $amount)
                    ->orWhere(function ($inner) use ($amount) {
                        $inner->whereNull('expected_amount')->where('amount', $amount);
                    });
            });

        if ($onlyPayment) {
            $query->where('id', $onlyPayment->id);
        }

        /** @var Payment|null $payment */
        $payment = $query->orderBy('created_at')->first();

        if (! $payment) {
            return ['status' => 'unmatched', 'payment_id' => null];
        }

        try {
            $payment->update([
                'bank_transaction_ref' => $ref,
                'email_verified_at' => now(),
            ]);

            $this->payments->activate(
                $payment->subscription,
                $payment,
                $payment->subscription->plan
            );

            Log::info('Subscription payment verified via BSI email', [
                'payment_id' => $payment->id,
                'amount' => $amount,
                'ref' => $ref,
            ]);

            return ['status' => 'matched', 'payment_id' => $payment->id];
        } catch (\Throwable $e) {
            Log::error('Failed activating subscription from email', ['error' => $e->getMessage()]);

            return ['status' => 'unmatched', 'payment_id' => null];
        }
    }

    protected function connect()
    {
        $host = config('subscription.email.imap_host');
        $port = config('subscription.email.imap_port');
        $user = config('subscription.email.username');
        $pass = config('subscription.email.password');

        $mailbox = sprintf('{%s:%d/imap/ssl/novalidate-cert}INBOX', $host, $port);

        return @imap_open($mailbox, $user, $pass);
    }

    /** @return array<int,int> */
    protected function searchMessages($connection): array
    {
        $from = config('subscription.email.from_address');
        $subject = config('subscription.email.subject');
        $days = config('subscription.email.lookback_days', 7);
        $since = date('d-M-Y', strtotime("-{$days} days"));

        $criteria = sprintf('FROM "%s" SUBJECT "%s" SINCE "%s"', $from, $subject, $since);
        $ids = imap_search($connection, $criteria, SE_UID);

        if (! $ids) {
            return [];
        }

        return array_map(fn ($uid) => imap_msgno($connection, $uid), $ids);
    }

    protected function fetchBody($connection, int $msgNum): string
    {
        $structure = imap_fetchstructure($connection, $msgNum);
        if (! $structure) {
            return imap_body($connection, $msgNum) ?: '';
        }

        $parts = $this->collectTextParts($connection, $msgNum, $structure);

        return implode("\n", array_filter($parts)) ?: (imap_body($connection, $msgNum) ?: '');
    }

    /** @return array<int,string> */
    protected function collectTextParts($connection, int $msgNum, $structure, string $prefix = ''): array
    {
        $texts = [];

        if (isset($structure->parts) && count($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $section = $prefix === '' ? (string) ($index + 1) : $prefix.'.'.($index + 1);
                $texts = array_merge($texts, $this->collectTextParts($connection, $msgNum, $part, $section));
            }

            return $texts;
        }

        $type = $structure->type ?? 0;
        $subtype = strtolower($structure->subtype ?? '');

        if ($type === 0 && in_array($subtype, ['plain', 'html'], true)) {
            $body = imap_fetchbody($connection, $msgNum, $prefix ?: '1');
            if (isset($structure->encoding)) {
                $body = $this->decodeBody($body, $structure->encoding);
            }
            if ($body) {
                $texts[] = $body;
            }
        }

        return $texts;
    }

    protected function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            ENCBASE64 => base64_decode($body) ?: $body,
            ENCQUOTEDPRINTABLE => quoted_printable_decode($body) ?: $body,
            default => $body,
        };
    }
}
