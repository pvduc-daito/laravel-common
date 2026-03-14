<?php

namespace Daito\Lib;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\RoundingMode;
use RuntimeException;

class DaitoMath
{
    const DEFAULT_SCALE = 12;

    public static function add($left, $right, $scale = self::DEFAULT_SCALE)
    {
        $bigDecimal = self::toBigDecimal($left)->plus(self::toBigDecimal($right));

        return self::normalize((string) $bigDecimal->toScale((int) $scale));
    }

    public static function sub($left, $right, $scale = self::DEFAULT_SCALE)
    {
        $bigDecimal = self::toBigDecimal($left)->minus(self::toBigDecimal($right));

        return self::normalize((string) $bigDecimal->toScale((int) $scale));
    }

    public static function mul($left, $right, $scale = self::DEFAULT_SCALE)
    {
        $bigDecimal = self::toBigDecimal($left)->multipliedBy(self::toBigDecimal($right));

        return self::normalize((string) $bigDecimal->toScale((int) $scale));
    }

    public static function div($left, $right, $scale = self::DEFAULT_SCALE)
    {
        $leftValue = self::toBigDecimal($left);
        $rightValue = self::toBigDecimal($right);

        if ($rightValue->isZero()) {
            throw new RuntimeException('Can not divide by zero.');
        }

        try {
            $bigDecimal = $leftValue->dividedBy($rightValue, (int) $scale, RoundingMode::HALF_UP);
        } catch (DivisionByZeroException $exception) {
            throw new RuntimeException('Can not divide by zero.');
        }

        return self::normalize((string) $bigDecimal);
    }

    public static function floor($value)
    {
        return (string) self::toBigDecimal($value)->toScale(0, RoundingMode::FLOOR);
    }

    public static function ceil($value)
    {
        return (string) self::toBigDecimal($value)->toScale(0, RoundingMode::CEILING);
    }

    public static function round($value, $scale = 0)
    {
        return self::normalize((string) self::toBigDecimal($value)->toScale((int) $scale, RoundingMode::HALF_UP));
    }

    public static function mulFloor($left, $right, $scale = self::DEFAULT_SCALE)
    {
        return self::floor(self::mul($left, $right, $scale));
    }

    private static function toBigDecimal($value)
    {
        if (is_int($value) || is_string($value)) {
            return BigDecimal::of((string) $value);
        }

        if (is_float($value)) {
            return BigDecimal::of(self::normalize(sprintf('%.14F', $value)));
        }

        throw new RuntimeException('DaitoMath only supports int, float, or numeric string.');
    }

    private static function normalize($number)
    {
        $number = (string) $number;
        if (strpos($number, '.') === false) {
            return $number;
        }

        $number = rtrim($number, '0');
        $number = rtrim($number, '.');

        if ($number === '-0') {
            return '0';
        }

        return $number === '' ? '0' : $number;
    }
}
