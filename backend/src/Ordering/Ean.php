<?php

namespace App\Ordering;

/** EAN-13 / EAN-8 barcodes: normalisation (digits only) and check digit (GS1 modulo 10, weights 3/1 from the right). */
final class Ean
{
    public static function normalize(?string $raw): ?string
    {
        if (null === $raw) {
            return null;
        }
        $digits = preg_replace('/[\s-]+/', '', $raw) ?? '';

        return '' === $digits ? null : $digits;
    }

    public static function isValid(string $ean): bool
    {
        if (!preg_match('/^(\d{8}|\d{13})$/', $ean)) {
            return false;
        }

        return self::checkDigit(substr($ean, 0, -1)) === (int) $ean[-1];
    }

    /** Check digit of the first 7 (EAN-8) or 12 (EAN-13) digits. */
    public static function checkDigit(string $body): int
    {
        $sum = 0;
        foreach (array_reverse(str_split($body)) as $i => $d) {
            $sum += (int) $d * (0 === $i % 2 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10;
    }
}
