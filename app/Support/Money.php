<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function toMinor(string|int|float|null $amount): int
    {
        $value = trim((string) $amount);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        $minor = ((int) $matches[2] * 100)
            + (int) str_pad($matches[3] ?? '', 2, '0');

        return ($matches[1] ?? '') === '-' ? -$minor : $minor;
    }

    public static function fromMinor(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $absolute = abs($amount);

        return $sign.intdiv($absolute, 100).'.'
            .str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
