<?php

namespace Daito\Lib;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use RuntimeException;

class DaitoNumber
{
    const DEFAULT_MAX_DECIMALS = 12;

    /**
     * Format number with configurable separators, prefix/suffix, and decimal behavior.
     *
     * Example:
     * DaitoNumber::format('1234567.00') => '1,234,567'
     * DaitoNumber::format('1234567.5', array('decimals' => 2)) => '1,234,567.50'
     */
    public static function format($number, array $arrOptions = array())
    {
        $arrConfig = array_merge(
            array(
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'prefix' => '',
                'suffix' => '',
                'decimals' => null,
                'trim_trailing_zeros' => true,
                'max_decimals' => self::DEFAULT_MAX_DECIMALS,
            ),
            $arrOptions
        );

        $numberString = self::toCanonicalString($number);
        $isNegative = strpos($numberString, '-') === 0;
        $unsigned = $isNegative ? substr($numberString, 1) : $numberString;

        if ($arrConfig['decimals'] !== null) {
            $decimals = (int) $arrConfig['decimals'];
            if ($decimals < 0) {
                throw new RuntimeException('decimals must be greater than or equal to 0.');
            }

            $unsigned = (string) BigDecimal::of($unsigned)->toScale($decimals, RoundingMode::HALF_UP);
        } else {
            $maxDecimals = (int) $arrConfig['max_decimals'];
            if ($maxDecimals < 0) {
                throw new RuntimeException('max_decimals must be greater than or equal to 0.');
            }

            $unsigned = (string) BigDecimal::of($unsigned)->toScale($maxDecimals, RoundingMode::HALF_UP);
            if ($arrConfig['trim_trailing_zeros']) {
                $unsigned = self::trimTrailingZeros($unsigned);
            }
        }

        $arrParts = explode('.', $unsigned, 2);
        $integerPart = self::addThousandsSeparator($arrParts[0], (string) $arrConfig['thousands_separator']);
        $decimalPart = isset($arrParts[1]) ? $arrParts[1] : '';

        $formattedNumber = $integerPart;
        if ($decimalPart !== '') {
            $formattedNumber .= (string) $arrConfig['decimal_separator'] . $decimalPart;
        }

        $formatted = (string) $arrConfig['prefix'] . $formattedNumber . (string) $arrConfig['suffix'];

        return $isNegative ? '-' . $formatted : $formatted;
    }

    /**
     * Format currency-friendly output (default 2 decimals, keep trailing zeros).
     *
     * Example:
     * DaitoNumber::formatCurrency('1234.5') => '1,234.50'
     * DaitoNumber::formatCurrency('1234.5', array('prefix' => '$')) => '$1,234.50'
     */
    public static function formatCurrency($number, array $arrOptions = array())
    {
        $arrCurrencyOptions = array_merge(
            array(
                'decimals' => 2,
                'trim_trailing_zeros' => false,
                'prefix' => '',
                'suffix' => '',
            ),
            $arrOptions
        );

        return self::format($number, $arrCurrencyOptions);
    }

    /**
     * Format percentage value.
     *
     * Example:
     * DaitoNumber::formatPercent('12.3456') => '12.35%'
     * DaitoNumber::formatPercent('0.1234', array('input_ratio' => true)) => '12.34%'
     */
    public static function formatPercent($number, array $arrOptions = array())
    {
        $arrPercentOptions = array_merge(
            array(
                'decimals' => 2,
                'trim_trailing_zeros' => true,
                'suffix' => '%',
                'input_ratio' => false,
            ),
            $arrOptions
        );

        $numberValue = $number;
        if ($arrPercentOptions['input_ratio']) {
            $numberValue = (string) BigDecimal::of(self::toCanonicalString($number))->multipliedBy('100');
        }

        unset($arrPercentOptions['input_ratio']);

        return self::format($numberValue, $arrPercentOptions);
    }

    private static function toCanonicalString($number)
    {
        if (is_int($number) || is_string($number)) {
            $numberString = trim((string) $number);
        } elseif (is_float($number)) {
            $numberString = self::trimTrailingZeros(sprintf('%.14F', $number));
        } else {
            throw new RuntimeException('DaitoNumber only supports int, float, or numeric string.');
        }

        if ($numberString === '' || !preg_match('/^[+-]?\d+(\.\d+)?$/', $numberString)) {
            throw new RuntimeException('Invalid number format.');
        }

        return (string) BigDecimal::of($numberString);
    }

    private static function addThousandsSeparator($integerPart, $thousandsSeparator)
    {
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', $thousandsSeparator, $integerPart);
    }

    private static function trimTrailingZeros($numberString)
    {
        if (strpos($numberString, '.') === false) {
            return $numberString;
        }

        $numberString = rtrim($numberString, '0');
        $numberString = rtrim($numberString, '.');

        return $numberString === '' ? '0' : $numberString;
    }
}
