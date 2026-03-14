<?php

namespace Daito\Lib\DaitoGoogleChat\Providers;

use Daito\Lib\DaitoGoogleChat\DaitoGoogleChatManager;
use Daito\Lib\DaitoGoogleChat\Services\DaitoGoogleChatWebhookClient;
use Illuminate\Support\ServiceProvider;

class DaitoGoogleChatProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/daito-google-chat.php',
            'daito-google-chat'
        );

        $this->app->singleton(DaitoGoogleChatWebhookClient::class, function () {
            return new DaitoGoogleChatWebhookClient();
        });

        $this->app->singleton(DaitoGoogleChatManager::class, function ($app) {
            return new DaitoGoogleChatManager(
                $app->make(DaitoGoogleChatWebhookClient::class)
            );
        });
    }

    public function boot(): void
    {
        $this->publishes(
            array(
                __DIR__ . '/../config/daito-google-chat.php' => config_path('daito-google-chat.php'),
            ),
            'daito-google-chat-config'
        );
    }
}
