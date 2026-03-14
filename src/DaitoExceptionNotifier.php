<?php

namespace Daito\Lib;

use Daito\Lib\DaitoExceptionNotifier\DaitoExceptionNotifierManager;
use RuntimeException;
use Throwable;

class DaitoExceptionNotifier
{
    public static function send(Throwable $throwable, array $arrContext = array(), $webhookUrl = null): array
    {
        return self::manager()->send($throwable, $arrContext, $webhookUrl);
    }

    public static function queue(Throwable $throwable, array $arrContext = array(), $webhookUrl = null): void
    {
        self::manager()->queue($throwable, $arrContext, $webhookUrl);
    }

    public static function notify(Throwable $throwable, array $arrContext = array(), $webhookUrl = null)
    {
        return self::manager()->notify($throwable, $arrContext, $webhookUrl);
    }

    private static function manager(): DaitoExceptionNotifierManager
    {
        if (!function_exists('app')) {
            throw new RuntimeException('Laravel app container is required for DaitoExceptionNotifier.');
        }

        $manager = app(DaitoExceptionNotifierManager::class);
        if (!$manager instanceof DaitoExceptionNotifierManager) {
            throw new RuntimeException('Can not resolve DaitoExceptionNotifierManager from container.');
        }

        return $manager;
    }
}
