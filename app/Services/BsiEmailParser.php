<?php

namespace App\Services;

class BsiEmailParser
{
    /** Parse notifikasi kredit BSI dari isi email. */
    public function parse(string $body): ?array
    {
        $text = html_entity_decode(strip_tags($body));
        $text = preg_replace('/\r\n|\r/', "\n", $text) ?? $text;

        if (! preg_match('/Nilai\s+Transaksi\s*:\s*IDR([\d,\.]+)/iu', $text, $amountMatch)) {
            return null;
        }

        $amount = $this->parseAmount($amountMatch[1]);

        $ref = null;
        if (preg_match('/Nomor\s+Transaksi\s*:\s*(\S+)/iu', $text, $refMatch)) {
            $ref = trim($refMatch[1]);
        }

        $account = null;
        if (preg_match('/Nomor\s+Rekening\s*:\s*(\S+)/iu', $text, $accMatch)) {
            $account = trim($accMatch[1]);
        }

        $transactionDate = null;
        if (preg_match('/Tanggal\s+Transaksi\s*:\s*(.+)$/ium', $text, $dateMatch)) {
            $transactionDate = trim(preg_replace('/\s+/', ' ', $dateMatch[1]));
        }

        if ($amount <= 0) {
            return null;
        }

        return [
            'amount' => $amount,
            'bank_transaction_ref' => $ref,
            'account' => $account,
            'transaction_date' => $transactionDate,
            'snippet' => mb_substr(trim($text), 0, 500),
        ];
    }

    public function parseAmount(string $raw): float
    {
        $clean = str_replace(',', '', trim($raw));

        return round((float) $clean, 2);
    }
}
