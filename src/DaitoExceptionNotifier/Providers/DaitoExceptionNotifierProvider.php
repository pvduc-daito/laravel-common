<?php

namespace Daito\Lib\DaitoExceptionNotifier\Providers;

use Daito\Lib\DaitoExceptionNotifier\DaitoExceptionNotifierManager;
use Daito\Lib\DaitoGoogleChat\DaitoGoogleChatManager;
use Illuminate\Support\ServiceProvider;

class DaitoExceptionNotifierProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/daito-exception-notifier.php',
            'daito-exception-notifier'
        );

        $this->app->singleton(DaitoExceptionNotifierManager::class, function ($app) {
            return new DaitoExceptionNotifierManager(
                $app->make(DaitoGoogleChatManager::class)
            );
        });
    }

    public function boot(): void
    {
        $this->publishes(
            array(
                __DIR__ . '/../config/daito-exception-notifier.php' => config_path('daito-exception-notifier.php'),
            ),
            'daito-exception-notifier-config'
        );
    }
}
