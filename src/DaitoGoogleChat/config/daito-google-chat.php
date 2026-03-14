<?php

return array(
    'enabled' => env('DAITO_GOOGLE_CHAT_ENABLED', true),
    'default_webhook_url' => env('DAITO_GOOGLE_CHAT_WEBHOOK_URL', ''),
    'validate_webhook_host' => env('DAITO_GOOGLE_CHAT_VALIDATE_WEBHOOK_HOST', true),
    'allowed_webhook_hosts' => array(
        'chat.googleapis.com',
    ),
    'timeout_seconds' => env('DAITO_GOOGLE_CHAT_TIMEOUT', 5),
    'connect_timeout_seconds' => env('DAITO_GOOGLE_CHAT_CONNECT_TIMEOUT', 3),
    'verify_ssl' => env('DAITO_GOOGLE_CHAT_VERIFY_SSL', true),
    'retry_times' => env('DAITO_GOOGLE_CHAT_RETRY_TIMES', 1),
    'retry_sleep_ms' => env('DAITO_GOOGLE_CHAT_RETRY_SLEEP_MS', 200),
    'queue_connection' => env('DAITO_GOOGLE_CHAT_QUEUE_CONNECTION', null),
    'queue_name' => env('DAITO_GOOGLE_CHAT_QUEUE_NAME', 'google-chat'),
    'queue_tries' => env('DAITO_GOOGLE_CHAT_QUEUE_TRIES', 3),
    'queue_backoff_seconds' => env('DAITO_GOOGLE_CHAT_QUEUE_BACKOFF', 10),
    'rate_limit_enabled' => env('DAITO_GOOGLE_CHAT_RATE_LIMIT_ENABLED', true),
    'rate_limit_max_jobs' => env('DAITO_GOOGLE_CHAT_RATE_LIMIT_MAX_JOBS', 20),
    'rate_limit_decay_seconds' => env('DAITO_GOOGLE_CHAT_RATE_LIMIT_DECAY_SECONDS', 60),
    'rate_limit_key' => env('DAITO_GOOGLE_CHAT_RATE_LIMIT_KEY', 'daito-google-chat:webhook'),
    'throw_on_error' => env('DAITO_GOOGLE_CHAT_THROW_ON_ERROR', false),
);
