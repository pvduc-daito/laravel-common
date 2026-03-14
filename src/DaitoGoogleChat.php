<?php

namespace Daito\Lib;

use Daito\Lib\DaitoGoogleChat\DaitoGoogleChatManager;
use RuntimeException;

class DaitoGoogleChat
{
    public static function sendText($text, array $arrContext = array(), $webhookUrl = null): array
    {
        return self::manager()->sendText($text, $arrContext, $webhookUrl);
    }

    public static function sendPayload(array $arrPayload, $webhookUrl = null): array
    {
        return self::manager()->sendPayload($arrPayload, $webhookUrl);
    }

    public static function sendCardV2(array $arrCardV2, $webhookUrl = null): array
    {
        return self::manager()->sendCardV2($arrCardV2, $webhookUrl);
    }

    public static function queueText($text, array $arrContext = array(), $webhookUrl = null): void
    {
        self::manager()->queueText($text, $arrContext, $webhookUrl);
    }

    public static function queuePayload(array $arrPayload, $webhookUrl = null): void
    {
        self::manager()->queuePayload($arrPayload, $webhookUrl);
    }

    public static function queueCardV2(array $arrCardV2, $webhookUrl = null): void
    {
        self::manager()->queueCardV2($arrCardV2, $webhookUrl);
    }

    private static function manager(): DaitoGoogleChatManager
    {
        if (!function_exists('app')) {
            throw new RuntimeException('Laravel app container is required for DaitoGoogleChat.');
        }

        $manager = app(DaitoGoogleChatManager::class);
        if (!$manager instanceof DaitoGoogleChatManager) {
            throw new RuntimeException('Can not resolve DaitoGoogleChatManager from container.');
        }

        return $manager;
    }
}
