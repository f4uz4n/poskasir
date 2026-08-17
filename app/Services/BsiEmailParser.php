<?php

namespace App\Services;

class BsiEmailParser
{
    /** Parse notifikasi kredit BSI dari isi email. */
    public function parse(string $body): ?array
    {
        $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\xc2\xa0/', ' ', $text) ?? $text; // NBSP
        $text = preg_replace('/\r\n|\r/', "\n", $text) ?? $text;
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

        $amount = null;
        $patterns = [
            '/Nilai\s+Transaksi\s*:\s*IDR\s*([\d\.,]+)/iu',
            '/Nilai\s+Transaksi\s*:\s*Rp\.?\s*([\d\.,]+)/iu',
            '/Nilai\s+Transaksi\s*:\s*([\d\.,]+)/iu',
            '/Nominal\s*:\s*IDR\s*([\d\.,]+)/iu',
            '/Nominal\s*:\s*Rp\.?\s*([\d\.,]+)/iu',
            '/Jumlah\s*:\s*IDR\s*([\d\.,]+)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $amountMatch)) {
                $amount = $this->parseAmount($amountMatch[1]);
                if ($amount > 0) {
                    break;
                }
            }
        }

        if ($amount === null || $amount <= 0) {
            return null;
        }

        $ref = null;
        if (preg_match('/Nomor\s+Transaksi\s*:\s*(\S+)/iu', $text, $refMatch)) {
            $ref = trim($refMatch[1], " \t\n\r\0\x0B.,;");
        } elseif (preg_match('/No\.?\s*Ref(?:erensi)?\s*:\s*(\S+)/iu', $text, $refMatch)) {
            $ref = trim($refMatch[1], " \t\n\r\0\x0B.,;");
        }

        $account = null;
        if (preg_match('/Nomor\s+Rekening\s*:\s*(\S+)/iu', $text, $accMatch)) {
            $account = preg_replace('/\D+/', '', $accMatch[1]) ?: trim($accMatch[1]);
        }

        $transactionDate = null;
        if (preg_match('/Tanggal\s+Transaksi\s*:\s*(.+)$/ium', $text, $dateMatch)) {
            $transactionDate = trim(preg_replace('/\s+/', ' ', $dateMatch[1]));
        }

        return [
            'amount' => $amount,
            'bank_transaction_ref' => $ref,
            'account' => $account,
            'transaction_date' => $transactionDate,
            'snippet' => mb_substr(trim($text), 0, 500),
        ];
    }

    /**
     * Normalisasi nominal BSI.
     * Contoh: 99.631 | 99.631,00 | 99,631.00 | 99631 → 99631
     */
    public function parseAmount(string $raw): float
    {
        $clean = preg_replace('/[^\d\.,]/', '', trim($raw)) ?? '';
        if ($clean === '') {
            return 0.0;
        }

        $hasComma = str_contains($clean, ',');
        $hasDot = str_contains($clean, '.');

        if ($hasComma && $hasDot) {
            // 99.631,00 (ID) atau 99,631.00 (US)
            if (strrpos($clean, ',') > strrpos($clean, '.')) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($hasComma) {
            // 99631,00 atau 99,631
            if (preg_match('/,\d{1,2}$/', $clean)) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($hasDot) {
            // 99631.00 atau 99.631 (pemisah ribuan Indonesia)
            if (preg_match('/\.\d{1,2}$/', $clean)) {
                // desimal: biarkan
            } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $clean)) {
                $clean = str_replace('.', '', $clean);
            } else {
                // fallback aman untuk pola ribuan
                $clean = str_replace('.', '', $clean);
            }
        }

        return round((float) $clean, 2);
    }
}
