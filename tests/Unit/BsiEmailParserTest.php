<?php

namespace Tests\Unit;

use App\Services\BsiEmailParser;
use PHPUnit\Framework\TestCase;

class BsiEmailParserTest extends TestCase
{
    public function test_parses_indonesian_thousand_separator(): void
    {
        $parser = new BsiEmailParser;
        $body = "Nilai Transaksi : IDR99.631\nNomor Transaksi : 123ABC\n";

        $parsed = $parser->parse($body);

        $this->assertNotNull($parsed);
        $this->assertSame(99631.0, $parsed['amount']);
        $this->assertSame('123ABC', $parsed['bank_transaction_ref']);
    }

    public function test_parses_us_format_amount(): void
    {
        $parser = new BsiEmailParser;
        $body = "Nilai Transaksi : IDR99,631.00\nNomor Transaksi : REF9\n";

        $parsed = $parser->parse($body);

        $this->assertNotNull($parsed);
        $this->assertSame(99631.0, $parsed['amount']);
    }

    public function test_parses_comma_decimal_format(): void
    {
        $parser = new BsiEmailParser;
        $body = "Nilai Transaksi : IDR99.631,00\nNomor Transaksi : REF10\n";

        $parsed = $parser->parse($body);

        $this->assertNotNull($parsed);
        $this->assertSame(99631.0, $parsed['amount']);
    }
}
