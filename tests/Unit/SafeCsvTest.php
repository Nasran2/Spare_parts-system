<?php

namespace Tests\Unit;

use App\Support\SafeCsv;
use PHPUnit\Framework\TestCase;

class SafeCsvTest extends TestCase
{
    public function test_formula_payloads_are_literal_and_negative_amounts_preserved(): void
    {
        $stream = fopen('php://temp', 'r+');
        SafeCsv::write($stream, ['=1+1', '+CMD()', "\t@SUM(1)", '-CMD()', '-12.50', -12.5, 'Normal']);
        rewind($stream);
        $this->assertSame(["'=1+1", "'+CMD()", "'\t@SUM(1)", "'-CMD()", '-12.50', '-12.5', 'Normal'], fgetcsv($stream));
        fclose($stream);
    }
}
