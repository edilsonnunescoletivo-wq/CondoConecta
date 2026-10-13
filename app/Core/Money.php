<?php

declare(strict_types=1);

namespace App\Core;

final class Money
{
    public static function parse(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^0-9,.-]/', '', $value) ?? '';
        if ($value === '' || $value === '-' || $value === ',' || $value === '.') {
            return null;
        }

        $commaPos = strrpos($value, ',');
        $dotPos = strrpos($value, '.');

        if ($commaPos !== false && $dotPos !== false) {
            $decimalSeparator = $commaPos > $dotPos ? ',' : '.';
            $groupingSeparator = $decimalSeparator === ',' ? '.' : ',';
            $value = str_replace($groupingSeparator, '', $value);
            if ($decimalSeparator === ',') {
                $value = str_replace(',', '.', $value);
            }
        } elseif ($commaPos !== false) {
            $parts = explode(',', $value);
            if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                $value = $parts[0] . '.' . $parts[1];
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($dotPos !== false) {
            $parts = explode('.', $value);
            if (count($parts) > 2) {
                $last = array_pop($parts);
                if ($last !== null && strlen($last) <= 2) {
                    $value = implode('', $parts) . '.' . $last;
                } else {
                    $value = implode('', $parts) . ($last ?? '');
                }
            } elseif (count($parts) === 2 && strlen($parts[1]) > 2) {
                $value = $parts[0] . $parts[1];
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        $amount = (float) $value;
        return is_finite($amount) ? $amount : null;
    }
}
