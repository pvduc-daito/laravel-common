<?php

return array(
    'enabled' => env('DAITO_EXCEPTION_NOTIFIER_ENABLED', true),
    'send_mode' => env('DAITO_EXCEPTION_NOTIFIER_SEND_MODE', 'queue'), // queue|sync
    'webhook_url' => env('DAITO_EXCEPTION_NOTIFIER_WEBHOOK_URL', ''),
    'card_title' => env('DAITO_EXCEPTION_NOTIFIER_CARD_TITLE', 'Exception Alert'),
    'loop_guard_enabled' => env('DAITO_EXCEPTION_NOTIFIER_LOOP_GUARD_ENABLED', true),
    'loop_guard_ttl_seconds' => env('DAITO_EXCEPTION_NOTIFIER_LOOP_GUARD_TTL_SECONDS', 10),
    'loop_guard_use_cache' => env('DAITO_EXCEPTION_NOTIFIER_LOOP_GUARD_USE_CACHE', true),
    'loop_guard_cache_prefix' => env('DAITO_EXCEPTION_NOTIFIER_LOOP_GUARD_CACHE_PREFIX', 'daito-exception-notifier:loop'),
    'loop_guard_skip_if_notifier_in_trace' => env('DAITO_EXCEPTION_NOTIFIER_LOOP_GUARD_SKIP_NOTIFIER_TRACE', true),
    'message_max_length' => env('DAITO_EXCEPTION_NOTIFIER_MESSAGE_MAX_LENGTH', 1000),
    'trace_mode' => env('DAITO_EXCEPTION_NOTIFIER_TRACE_MODE', 'smart'), // smart|app_only|no_vendor|class_prefix_only
    'trace_class_prefixes' => array('App\\'),
    'trace_include_first_app_frame' => env('DAITO_EXCEPTION_NOTIFIER_TRACE_INCLUDE_FIRST_APP_FRAME', true),
    'trace_max_lines' => env('DAITO_EXCEPTION_NOTIFIER_TRACE_MAX_LINES', 8),
    'trace_max_length' => env('DAITO_EXCEPTION_NOTIFIER_TRACE_MAX_LENGTH', 2500),
    'trace_skip_vendor' => env('DAITO_EXCEPTION_NOTIFIER_TRACE_SKIP_VENDOR', true),
);
