<?php

namespace App\Support;

class IndonesianPhoneNumber
{
    public static function normalize(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/^(?:\+62|62|0?8)[0-9 -]*[0-9]$/', $value)) {
            return null;
        }

        $digits = str_replace([' ', '-'], '', ltrim($value, '+'));

        if (str_starts_with($digits, '08')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return preg_match('/^628[0-9]{7,12}$/', $digits) ? $digits : null;
    }
}
