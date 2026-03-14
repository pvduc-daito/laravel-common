<?php

namespace Daito\Lib;

use RuntimeException;

class DaitoString
{
    const UTF8 = 'UTF-8';
    const NKF_SOURCE_ENCODINGS = 'SJIS-win,CP932,Shift_JIS,EUC-JP,UTF-8';

    /**
     * Collapse multiple spaces/tabs/newlines into a single ASCII space.
     *
     * Example:
     * DaitoString::collapseSpaces("a   b\t\tc") => "a b c"
     */
    public static function collapseSpaces($input)
    {
        return preg_replace('/\s+/u', ' ', (string) $input);
    }

    /**
     * Convert full-width Japanese spaces to ASCII spaces.
     *
     * Example:
     * DaitoString::normalizeFullWidthSpace("A　B") => "A B"
     */
    public static function normalizeFullWidthSpace($input)
    {
        return str_replace('　', ' ', (string) $input);
    }

    /**
     * Convert half-width kana to full-width kana.
     *
     * Example:
     * DaitoString::toFullWidthKana("ｶﾀｶﾅ") => "カタカナ"
     */
    public static function toFullWidthKana($text)
    {
        return mb_convert_kana((string) $text, 'KV', self::UTF8);
    }

    /**
     * Convert full-width kana to half-width kana.
     *
     * Example:
     * DaitoString::toHalfWidthKana("カタカナ") => "ｶﾀｶﾅ"
     */
    public static function toHalfWidthKana($text)
    {
        return mb_convert_kana((string) $text, 'kV', self::UTF8);
    }

    /**
     * Split text by normalized spaces (full-width/extra spaces are handled).
     *
     * Example:
     * DaitoString::splitBySpace("A　 B   C") => array("A", "B", "C")
     */
    public static function splitBySpace($string)
    {
        $normalized = self::normalizeFullWidthSpace((string) $string);
        $normalized = trim(self::collapseSpaces($normalized));

        if ($normalized === '') {
            return array();
        }

        return explode(' ', $normalized);
    }

    /**
     * Convert Hiragana to Katakana.
     *
     * Example:
     * DaitoString::toKatakana("ひらがな") => "ヒラガナ"
     */
    public static function toKatakana($input)
    {
        return mb_convert_kana((string) $input, 'C', self::UTF8);
    }

    /**
     * Check whether the input contains Japanese characters.
     *
     * Example:
     * DaitoString::isJapanese("abc日本語") => true
     * DaitoString::isJapanese("abcdef") => false
     */
    public static function isJapanese($input)
    {
        return preg_match('/[\x{3040}-\x{30FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{FF66}-\x{FF9D}]/u', (string) $input) === 1;
    }

    /**
     * Convert Japanese-encoded text file to UTF-8.
     * Prefer nkf when available, fallback to mb_convert_encoding.
     * To install nkf, you can use the following command:
     * sudo apt install nkf
     * Example:
     * DaitoString::convertToUtf8('/tmp/source.csv');
     * DaitoString::convertToUtf8('/tmp/source.csv', '/tmp/out', 'source_utf8.csv', 1);
     */
    public static function convertToUtf8($sourceFile, $destDir = '', $fileName = '', $isBk = 0)
    {
        $sourceFilePath = (string) $sourceFile;
        if (!is_file($sourceFilePath)) {
            throw new RuntimeException('Source file does not exist: ' . $sourceFilePath);
        }

        $targetDirectory = $destDir ? (string) $destDir : dirname($sourceFilePath);
        if (!is_dir($targetDirectory)) {
            throw new RuntimeException('Destination directory does not exist: ' . $targetDirectory);
        }

        $targetFileName = $fileName ? (string) $fileName : basename($sourceFilePath);
        $destFile = $targetDirectory . DIRECTORY_SEPARATOR . $targetFileName;

        if ((string) realpath($sourceFilePath) === (string) realpath($destFile)) {
            $destFile .= '_' . time();
        }

        if ($isBk) {
            $backupFile = $targetDirectory . DIRECTORY_SEPARATOR . $targetFileName . '.bak';
            if (!copy($sourceFilePath, $backupFile)) {
                throw new RuntimeException('Can not create backup file: ' . $backupFile);
            }
        }

        $utf8Content = self::convertJapaneseTextToUtf8($sourceFilePath);
        if (file_put_contents($destFile, $utf8Content) === false) {
            throw new RuntimeException('Can not write destination file: ' . $destFile);
        }

        return $destFile;
    }

    private static function convertJapaneseTextToUtf8($sourceFilePath)
    {
        if (self::canUseNkf()) {
            $nkfContent = self::convertFileByNkf($sourceFilePath);
            if ($nkfContent !== null) {
                return $nkfContent;
            }
        }

        return self::convertFileToUtf8ByMbstring($sourceFilePath);
    }

    private static function canUseNkf()
    {
        if (!function_exists('shell_exec')) {
            return false;
        }

        $disabledFunctions = (string) ini_get('disable_functions');
        if ($disabledFunctions !== '' && strpos($disabledFunctions, 'shell_exec') !== false) {
            return false;
        }

        $output = @shell_exec('nkf --version');

        return is_string($output) && $output !== '';
    }

    private static function convertFileByNkf($sourceFilePath)
    {
        $command = 'nkf -w -- ' . escapeshellarg($sourceFilePath);
        $output = @shell_exec($command);

        return is_string($output) ? $output : null;
    }

    /**
     * Convert a file content to UTF-8 using mbstring.
     *
     * Example:
     * DaitoString::convertFileToUtf8ByMbstring('/tmp/source_sjis.txt');
     */
    public static function convertFileToUtf8ByMbstring($sourceFilePath)
    {
        $content = file_get_contents($sourceFilePath);
        if ($content === false) {
            throw new RuntimeException('Can not read source file: ' . $sourceFilePath);
        }

        return self::convertTextToUtf8ByMbstring($content);
    }

    /**
     * Convert raw text to UTF-8 using mbstring.
     *
     * Example:
     * DaitoString::convertTextToUtf8ByMbstring($rawText);
     */
    public static function convertTextToUtf8ByMbstring($text)
    {
        $utf8Content = mb_convert_encoding((string) $text, self::UTF8, self::NKF_SOURCE_ENCODINGS);
        if ($utf8Content === false) {
            throw new RuntimeException('Can not convert text to UTF-8.');
        }

        return $utf8Content;
    }
}