# How to use package

## 1) Add repository to composer.json
```json
"repositories": [
  {
    "type": "vcs",
    "url": "git@github.com:pvduc-daito/laravel-common.git"
  }
]
```

## 2) Install package

### Stable (recommended - install by tag)
```bash
composer require daito/lib:^1.0
```

### Dev (testing latest branch code)
```bash
composer require daito/lib:dev-main
```

## 3) Update package

### Stable
```bash
composer update daito/lib
```

### Dev branch
```bash
composer update daito/lib
```

## Release by tag

After code is ready on release branch (for example `production`), create and push tag:

```bash
git tag v1.0.0
git push origin v1.0.0
```

Then projects using this package can update to the new stable version:

```bash
composer require daito/lib:^1.0
# or
composer update daito/lib
```

## Performance note (DaitoMath)

`DaitoMath` uses `brick/math` for precise decimal calculations (financial-safe).

- It can run without extra PHP extensions.
- For better performance on production, enable one of:
  - `ext-gmp` (recommended)
  - `ext-bcmath`
- `brick/math` will automatically use the fastest available calculator at runtime.

Quick check:

```bash
php -m | grep -Ei "gmp|bcmath"
```

Install extensions if missing:

```bash
# Ubuntu / Debian
sudo apt-get update
sudo apt-get install -y php-gmp php-bcmath
sudo systemctl restart php8.2-fpm || sudo systemctl restart apache2
```

```bash
# CentOS / RHEL / Rocky / AlmaLinux
sudo yum install -y php-gmp php-bcmath
sudo systemctl restart php-fpm || sudo systemctl restart httpd
```

```powershell
# Windows (php.ini)
# 1) Open php.ini
# 2) Enable these lines:
extension=gmp
extension=bcmath
# 3) Restart web server / PHP-FPM service
```

## DaitoString

### convertToUtf8 (Japanese text file -> UTF-8)

`DaitoString::convertToUtf8()` converts Japanese-encoded text files to UTF-8.

- Prefer `nkf` when available
- Fallback to `mb_convert_encoding` when `nkf` is unavailable

Install `nkf` (Ubuntu/Debian):

```bash
sudo apt install nkf
```

Example:

```php
DaitoString::convertToUtf8('/tmp/source.csv');
DaitoString::convertToUtf8('/tmp/source.csv', '/tmp/out', 'source_utf8.csv', 1);
```

## DaitoGoogleChat (Laravel webhook utility)

### 1) Publish config
```bash
php artisan vendor:publish --tag=daito-google-chat-config
```

This creates `config/daito-google-chat.php`.

### 2) Minimal `.env` setup
```dotenv
DAITO_GOOGLE_CHAT_ENABLED=true
DAITO_GOOGLE_CHAT_WEBHOOK_URL=https://chat.googleapis.com/v1/spaces/.../messages?key=...&token=...
DAITO_GOOGLE_CHAT_VERIFY_SSL=true
DAITO_GOOGLE_CHAT_QUEUE_NAME=google-chat
DAITO_GOOGLE_CHAT_RATE_LIMIT_ENABLED=true
DAITO_GOOGLE_CHAT_RATE_LIMIT_MAX_JOBS=20
DAITO_GOOGLE_CHAT_RATE_LIMIT_DECAY_SECONDS=60
```

### 3) Send immediately
```php
use Daito\Lib\DaitoGoogleChat;

DaitoGoogleChat::sendText('Deploy success', array(
    'service' => 'billing-api',
    'env' => 'production',
));
```

### 4) Send via queue
```php
use Daito\Lib\DaitoGoogleChat;

DaitoGoogleChat::queueText('Large import finished', array(
    'job_id' => 12345,
    'duration' => '02:31',
));
```

### 5) Advanced payload
```php
DaitoGoogleChat::sendPayload(array(
    'text' => 'Custom payload from daito/lib',
));
```

### 6) cardV2 (send immediately)
```php
use Daito\Lib\DaitoGoogleChat;

$arrCardV2 = array(
    'cardId' => 'deploy-status',
    'card' => array(
        'header' => array(
            'title' => 'Deploy Success',
            'subtitle' => 'billing-api / production',
        ),
        'sections' => array(
            array(
                'widgets' => array(
                    array(
                        'decoratedText' => array(
                            'topLabel' => 'Version',
                            'text' => 'v2.3.1',
                        ),
                    ),
                    array(
                        'decoratedText' => array(
                            'topLabel' => 'Duration',
                            'text' => '01:45',
                        ),
                    ),
                ),
            ),
        ),
    ),
);

DaitoGoogleChat::sendCardV2($arrCardV2);
```

### 7) cardV2 (send via queue)
```php
DaitoGoogleChat::queueCardV2($arrCardV2);
```

### 8) Anti-spam notes (recommended for production)

- Queue job is rate-limited globally by cache key (`daito-google-chat:webhook`).
- Default limit is `20` jobs / `60` seconds (configurable).
- Keep `queue_backoff_seconds` as-is to retry safely on transient errors.

Main config keys in `config/daito-google-chat.php`:

- `rate_limit_enabled`
- `rate_limit_max_jobs`
- `rate_limit_decay_seconds`
- `rate_limit_key`
- `queue_name` (default: `google-chat`)

### 9) Run dedicated queue worker for google-chat

Run a separate worker to isolate notification throughput:

```bash
php artisan queue:work --queue=google-chat --sleep=1 --tries=3 --timeout=30
```

Supervisor example:

```ini
[program:laravel-google-chat-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=google-chat --sleep=1 --tries=3 --timeout=30
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/laravel-google-chat-worker.log
```

### 10) Local WAMP + self-signed SSL (for webhook calls)

If your local machine uses a self-signed certificate, keep `DAITO_GOOGLE_CHAT_VERIFY_SSL=true` and trust your local CA/cert in Windows.

1. Download https://curl.se/ca/cacert.pem
2. Put cacert.pem into C:\wamp64\bin\php
3. Open php.ini and Edit as:
```
curl.cainfo = "C:\php\extras\ssl\cacert.pem"
openssl.cafile = "C:\php\extras\ssl\cacert.pem"
```

4. Restart apache

Quick local-only fallback (not recommended long-term):

```dotenv
DAITO_GOOGLE_CHAT_VERIFY_SSL=false
```

Use this only for temporary debugging on local environment. Never disable SSL verification on production.

## DaitoExceptionNotifier (separate reusable module)

### 1) Publish config
```bash
php artisan vendor:publish --tag=daito-exception-notifier-config
```

This creates `config/daito-exception-notifier.php`.

### 2) Minimal `.env` setup
```dotenv
DAITO_EXCEPTION_NOTIFIER_ENABLED=true
DAITO_EXCEPTION_NOTIFIER_SEND_MODE=queue
DAITO_EXCEPTION_NOTIFIER_LOOP_GUARD_ENABLED=true
DAITO_EXCEPTION_NOTIFIER_LOOP_GUARD_TTL_SECONDS=30
DAITO_EXCEPTION_NOTIFIER_TRACE_MODE=smart
DAITO_EXCEPTION_NOTIFIER_TRACE_MAX_LINES=8
DAITO_EXCEPTION_NOTIFIER_TRACE_SKIP_VENDOR=true
DAITO_EXCEPTION_NOTIFIER_TRACE_INCLUDE_FIRST_APP_FRAME=true
```

### 3) Auto notify on exception (HTTP + CLI)

Use Laravel exception handler to automatically send exception cardV2:

```php
<?php

namespace App\Exceptions;

use Daito\Lib\DaitoExceptionNotifier;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register()
    {
        $this->reportable(function (Throwable $throwable) {
            DaitoExceptionNotifier::notify($throwable);
        });
    }
}
```

`DaitoExceptionNotifier::notify()` supports both route/controller errors and CLI command errors because Laravel routes both through the same exception handler.

If you want explicit mode:

```php
DaitoExceptionNotifier::queue($throwable); // queue
DaitoExceptionNotifier::send($throwable);  // sync immediate
```

Exception cardV2 includes:

- `time`
- `action`
- `file`
- `line`
- `message`
- compact `trace` (top useful frames only, configurable)
- `first_app_frame` (when available)

Trace filter strategy (`DAITO_EXCEPTION_NOTIFIER_TRACE_MODE`):

- `smart` (recommended): prefer app frames, then non-vendor frames, then fallback
- `app_only`: only frames that belong to `app/` or class prefix `App\`
- `no_vendor`: remove `vendor` frames
- `class_prefix_only`: only frames by configured class prefixes (`trace_class_prefixes`)

### 4) Built-in loop guard (recommended default)

To avoid recursive notify loops when Google Chat send itself throws exceptions, this module has a built-in circuit-breaker:

- re-entrant guard in the same process/request
- short TTL dedupe by exception fingerprint (file+line+message+action)
- optional cache-based dedupe across workers/processes

Main config keys:

- `loop_guard_enabled`
- `loop_guard_ttl_seconds`
- `loop_guard_use_cache`
- `loop_guard_cache_prefix`
- `loop_guard_skip_if_notifier_in_trace`

## QueryLog (Laravel shared package)

> **Provider registration note**
> 
> This package supports Laravel package auto-discovery via `composer.json`:
> `Daito\Lib\DaitoQueryLog\Providers\DaitoQueryLogProvider`.
> 
> In normal cases, child projects do **not** need to register the provider manually.
> If a child project uses `"dont-discover"` for this package, add it manually in
> `config/app.php`:
> 
> ```php
> 'providers' => [
>   // ...
>   Daito\Lib\DaitoQueryLog\Providers\DaitoQueryLogProvider::class,
> ],
> ```

### 1) Publish config in child project
```bash
php artisan vendor:publish --tag=daito-query-log-config
```

This creates `config/daito-query-log.php` so each project can tune its own settings.

### 2) Publish migration in child project
```bash
php artisan vendor:publish --tag=daito-query-log-migrations
php artisan migrate
```

This publishes a production-oriented migration for `log_queries` with key indexes:

- `query_at`
- `query_type`
- `connection`
- `user_id`
- composite indexes for common filter windows

### 3) Minimal table fields

Use your own migration and ensure these columns exist in the configured table (`daito-query-log.table`):

- `action` (string)
- `query` (longText/text)
- `query_type` (string, example: `insert`, `update`, `delete`, `replace`)
- `query_time` (float/double)
- `query_at` (datetime)
- `query_order` (int)
- `connection` (string)
- `ip` (nullable string)
- `user_id` (nullable string/int)
- `is_screen` (tinyint/bool)

### 4) Important config for production

- `enable`: enable/disable query log
- `min_time`: skip very fast queries (ms)
- `sample_rate`: sampling percent (`0-100`) to reduce high-traffic load
- `chunk`: batch size per queue job
- `max_queries_per_request`: hard limit per request
- `skip_route_patterns`: wildcard route/url patterns to skip
- `skip_command_patterns`: wildcard console command patterns to skip
- `mask_sensitive_bindings`: mask sensitive values in SQL bindings
- `sensitive_keywords`: keyword list used for masking
- `masked_value`: replacement text for sensitive bindings

### 5) Behavior highlights

- Query buffer is separated by DB connection.
- Buffer is flushed when transaction commits (outermost level).
- Buffer is cleared on rollback (rolled-back queries are not logged).
- Write-query detection supports CTE (`WITH ... UPDATE/INSERT/...`) and skips read queries.
- `query_type` stores action text directly (`insert`, `update`, `delete`, `replace`, `upsert`).