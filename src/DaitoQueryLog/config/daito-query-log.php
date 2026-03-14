<?php

return array(
    'enable' => env('ENABLE_QUERY_LOG', true),
    'log_on_console' => env('QUERY_LOG_ON_CONSOLE', false),
    'sample_rate' => env('QUERY_LOG_SAMPLE_RATE', 100), // 0-100 (%)
    'chunk' => env('QUERY_LOG_CHUNK', 200),
    'max_queries_per_request' => env('QUERY_LOG_MAX_PER_REQUEST', 1000),
    'min_time' => env('QUERY_LOG_MIN_TIME', 0),
    'connection' => env('QUERY_LOG_CONNECTION', 'query_log'),
    'table' => env('QUERY_LOG_TABLE', 'log_queries'),
    'queue_connection' => env('QUERY_LOG_QUEUE_CONNECTION', null),
    'queue_name' => env('QUERY_LOG_QUEUE_NAME', null),
    'max_sql_length' => env('QUERY_LOG_MAX_SQL_LENGTH', 4000),
    'mask_sensitive_bindings' => env('QUERY_LOG_MASK_SENSITIVE_BINDINGS', true),
    'sensitive_keywords' => array(
        'password',
        'passwd',
        'pwd',
        'token',
        'secret',
        'api_key',
        'apikey',
        'authorization',
        'cookie',
        'credit_card',
        'card_number',
        'cvv',
        'pin',
    ),
    'masked_value' => '***',
    'skip_route_patterns' => array(
        'horizon*',
        'telescope*',
        '_debugbar*',
    ),
    'skip_command_patterns' => array(
        'queue:*',
        'horizon*',
        'schedule:run',
    ),
    'ignore_tables' => array(
        'log_queries',
        'jobs',
        'failed_jobs',
        'migrations',
        'mst_batch',
    ),
);
