<?php

namespace App\Services;

use InvalidArgumentException;

final class DecimalMath
{
    public const SCALE = 10000;

    public static function parse(string|int|float|null $value, int $decimals = 4): int
    {
        $raw = trim((string) ($value ?? '0'));
        if ($raw === '') {
            return 0;
        }

        if (! preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $raw, $matches)) {
            throw new InvalidArgumentException("Invalid decimal value: {$raw}");
        }

        $negative = ($matches[1] ?? '') === '-';
        $whole = ltrim($matches[2] ?: '0', '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = $matches[3] ?? '';
        $kept = substr(str_pad($fraction, $decimals, '0'), 0, $decimals);
        $nextDigit = (int) ($fraction[$decimals] ?? 0);

        $scale = 10 ** $decimals;
        $result = ((int) $whole * $scale) + (int) $kept;
        if ($nextDigit >= 5) {
            $result++;
        }

        return $negative ? -$result : $result;
    }

    public static function format(int $value, int $decimals = 4): string
    {
        $negative = $value < 0;
        $value = abs($value);
        $scale = 10 ** $decimals;
        $whole = intdiv($value, $scale);
        $fraction = str_pad((string) ($value % $scale), $decimals, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').$whole.($decimals > 0 ? '.'.$fraction : '');
    }

    public static function currency(int $value): string
    {
        $minor = self::roundDiv($value, 100);

        return self::format($minor, 2);
    }

    public static function multiply(int $left, int $right): int
    {
        return self::roundDiv($left * $right, self::SCALE);
    }

    public static function percentage(int $amount, int $rate): int
    {
        return self::roundDiv($amount * $rate, 100 * self::SCALE);
    }

    public static function roundDiv(int $numerator, int $denominator): int
    {
        if ($denominator === 0) {
            throw new InvalidArgumentException('Cannot divide by zero.');
        }

        $negative = ($numerator < 0) xor ($denominator < 0);
        $numerator = abs($numerator);
        $denominator = abs($denominator);
        $result = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;
        if ($remainder * 2 >= $denominator) {
            $result++;
        }

        return $negative ? -$result : $result;
    }
}
