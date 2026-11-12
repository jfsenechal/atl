<?php

namespace AcMarche\Mercredi\Security;

use AcMarche\Mercredi\Validator\PasswordRequirements;

final class PasswordGenerator
{
    // Look-alike characters (0/O, 1/l/I) are left out: these passwords are sent by email and typed by hand.
    private const string LOWER = 'abcdefghijkmnpqrstuvwxyz';
    private const string UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const string DIGITS = '23456789';

    /**
     * Generates a password that satisfies {@see PasswordRequirements}.
     */
    public static function generatePassword(): string
    {
        $chars = [
            self::pick(self::LOWER),
            self::pick(self::UPPER),
            self::pick(self::DIGITS),
        ];

        $all = self::LOWER.self::UPPER.self::DIGITS;
        while (\count($chars) < PasswordRequirements::MIN_LENGTH) {
            $chars[] = self::pick($all);
        }

        // Fisher-Yates shuffle with a CSPRNG so the guaranteed characters are not always in front
        for ($i = \count($chars) - 1; $i > 0; --$i) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    private static function pick(string $alphabet): string
    {
        return $alphabet[random_int(0, \strlen($alphabet) - 1)];
    }
}
