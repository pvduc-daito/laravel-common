<?php
namespace Daito\Lib;

use Milon\Barcode\DNS1D;
use Milon\Barcode\DNS2D;

class DaitoBarcode {
    public static function getJan13Number($jan12)
    {
        // Kiểm tra định dạng
        if (!preg_match('/^\d{12}$/', $jan12)) {
            return $jan12;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $jan12[$i];
            $sum += $i % 2 === 0 ? $digit : $digit * 3;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return $jan12 . $checkDigit;
    }
    public static function isJanEAN13($jan)
    {
        if (!preg_match('/^\d{13}$/', $jan)) {
            return false;
        }

        $jan12 = substr($jan, 0, 12);
        $ean13Jan = self::getJan13Number($jan12);
        return $ean13Jan === $jan;
    }

    public static function getJan8Number($jan7)
    {
        if (!preg_match('/^\d{7}$/', $jan7)) {
            return $jan7;
        }

        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $digit = (int) $jan7[$i];
            $sum += $i % 2 === 0 ? $digit * 3 : $digit;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return $jan7 . $checkDigit;
    }

    public static function isJanEAN8($jan)
    {
        if (!preg_match('/^\d{8}$/', $jan)) {
            return false;
        }

        $jan7 = substr($jan, 0, 7);
        $ean8Jan = self::getJan8Number($jan7);

        return $ean8Jan === $jan;
    }

    public static function getBarcodeTypeJan($jan)
    {
        if (self::isJanEAN8($jan)) {
            return 'EAN8';
        }

        if (self::isJanEAN13($jan)) {
            return 'EAN13';
        }

        return 'C128';
    }

    public static function generateBarcodeJan($jan)
    {
        $typeJan = self::getBarcodeTypeJan($jan);

        if ($typeJan === 'EAN13') {
            $jan = substr($jan, 0, 12);
        } elseif ($typeJan === 'EAN8') {
            $jan = substr($jan, 0, 7);
        }

        $dns1d = new DNS1D();

        return $dns1d->getBarcodePNG($jan, $typeJan);
    }

    public static function generateBarcodeC128($text)
    {
        $dns1d = new DNS1D();

        return $dns1d->getBarcodePNG($text, 'C128');
    }

    public static function generateBarcodeC39($text)
    {
        $dns1d = new DNS1D();

        return $dns1d->getBarcodePNG($text, 'C39');
    }

    public static function generateBarcodeEAN13($ean13)
    {
        $dns1d = new DNS1D();

        return $dns1d->getBarcodePNG($ean13, 'EAN13');
    }

    public static function generateBarcodeEAN8($ean8)
    {
        $dns1d = new DNS1D();

        return $dns1d->getBarcodePNG($ean8, 'EAN8');
    }

    public static function generateBarcodeQrCode($text)
    {
        $dns2d = new DNS2D();

        return $dns2d->getBarcodePNG($text, 'QRCODE');
    }
}