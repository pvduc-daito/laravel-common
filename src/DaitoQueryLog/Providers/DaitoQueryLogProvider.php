<?php

namespace Daito\Lib\DaitoQueryLog\Providers;

use Carbon\Carbon;
use Daito\Lib\DaitoQueryLog\Jobs\DaitoSaveQueryLogJob;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;

class DaitoQueryLogProvider extends ServiceProvider
{
    private $arrQueriesByConnection = array();
    private $arrLoggedCountsByConnection = array();

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/daito-query-log.php',
            'daito-query-log'
        );
    }

    public function boot(): void
    {
        $this->registerPublishableResources();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if (!$this->isEnabled()) {
            return;
        }

        $this->registerTransactionLifecycleListeners();

        DB::listen(function (QueryExecuted $query) {
            if (!$this->isEnabled()) {
                return;
            }

            if (!$this->shouldLogInCurrentRuntime()) {
                return;
            }

            if ((float) $query->time < (float) config('daito-query-log.min_time', 0)) {
                return;
            }

            if (!$this->isPassedSampling()) {
                return;
            }

            if ($this->shouldSkipCurrentContext()) {
                return;
            }

            $queryVerb = $this->detectWriteVerb($query->sql);
            if ($queryVerb === null) {
                return;
            }

            if ($this->isIgnoredTableSql($query->sql)) {
                return;
            }

            $connectionName = $query->connectionName ?: $query->connection->getName();
            if (!$this->canLogMoreQueries($connectionName)) {
                return;
            }

            $arrPayload = $this->buildPayload($query, $queryVerb, $connectionName);
            $this->appendQuery($connectionName, $arrPayload);

            if (!$this->isInTransaction($query->connection)) {
                $this->flushConnectionBuffer($connectionName);
            }
        });

        if (method_exists($this->app, 'terminating')) {
            call_user_func(array($this->app, 'terminating'), function () {
                $this->flushAllBuffers();
            });
        }
    }

    private function registerTransactionLifecycleListeners(): void
    {
        Event::listen(TransactionCommitted::class, function (TransactionCommitted $event) {
            if ($event->connection->transactionLevel() !== 0) {
                return;
            }

            $this->flushConnectionBuffer($event->connection->getName(), true);
        });

        Event::listen(TransactionRolledBack::class, function (TransactionRolledBack $event) {
            if ($event->connection->transactionLevel() !== 0) {
                return;
            }

            $this->clearConnectionBuffer($event->connection->getName());
        });
    }

    private function appendQuery($connectionName, array $arrPayload): void
    {
        if (!isset($this->arrQueriesByConnection[$connectionName])) {
            $this->arrQueriesByConnection[$connectionName] = array();
        }

        $this->arrQueriesByConnection[$connectionName][] = $arrPayload;
        $this->arrLoggedCountsByConnection[$connectionName] = ($this->arrLoggedCountsByConnection[$connectionName] ?? 0) + 1;
    }

    private function flushConnectionBuffer($connectionName, $force = false): void
    {
        $arrBuffer = $this->arrQueriesByConnection[$connectionName] ?? array();
        if ($arrBuffer === array()) {
            return;
        }

        $chunkSize = max(1, (int) config('daito-query-log.chunk', 200));
        if (!$force && count($arrBuffer) < $chunkSize) {
            return;
        }

        foreach (array_chunk($arrBuffer, $chunkSize) as $arrQueries) {
            $job = new DaitoSaveQueryLogJob($arrQueries);
            if (config('daito-query-log.queue_connection') !== null) {
                $job->onConnection(config('daito-query-log.queue_connection'));
            }
            if (config('daito-query-log.queue_name') !== null) {
                $job->onQueue(config('daito-query-log.queue_name'));
            }

            dispatch($job);
        }

        $this->arrQueriesByConnection[$connectionName] = array();
    }

    private function flushAllBuffers(): void
    {
        foreach (array_keys($this->arrQueriesByConnection) as $connectionName) {
            $this->flushConnectionBuffer($connectionName, true);
        }
    }

    private function clearConnectionBuffer($connectionName): void
    {
        $this->arrQueriesByConnection[$connectionName] = array();
    }

    private function canLogMoreQueries($connectionName): bool
    {
        $maxQueries = max(1, (int) config('daito-query-log.max_queries_per_request', 1000));
        $currentCount = $this->arrLoggedCountsByConnection[$connectionName] ?? 0;

        return $currentCount < $maxQueries;
    }

    private function isIgnoredTableSql($sql): bool
    {
        $arrIgnoreTables = (array) config('daito-query-log.ignore_tables', array());
        if ($arrIgnoreTables === array()) {
            return false;
        }

        foreach ($arrIgnoreTables as $tableName) {
            $tablePattern = '/\b`?' . preg_quote((string) $tableName, '/') . '`?\b/i';
            if (preg_match($tablePattern, $sql) === 1) {
                return true;
            }
        }

        return false;
    }

    private function detectWriteVerb($sql)
    {
        $normalizedSql = trim($this->stripLeadingSqlComments((string) $sql));
        if ($normalizedSql === '') {
            return null;
        }

        if (preg_match('/^(insert|update|delete|replace|upsert)\b/i', $normalizedSql, $arrMatches) === 1) {
            return strtolower($arrMatches[1]);
        }

        if (preg_match('/^with\b/i', $normalizedSql) === 1
            && preg_match('/\b(insert|update|delete|replace|upsert)\b/i', $normalizedSql, $arrMatches) === 1
        ) {
            return strtolower($arrMatches[1]);
        }

        return null;
    }

    private function stripLeadingSqlComments($sql)
    {
        $cleanSql = preg_replace('/^\s*(\/\*.*?\*\/\s*)+/s', '', $sql);
        if ($cleanSql === null) {
            return $sql;
        }

        return preg_replace('/^\s*(--[^\r\n]*[\r\n]\s*)+/s', '', $cleanSql) ?: $cleanSql;
    }

    private function buildPayload(QueryExecuted $query, $queryVerb, $connectionName): array
    {
        $rawSql = $this->interpolateSql($query->sql, $query->bindings);
        $maxSqlLength = max(256, (int) config('daito-query-log.max_sql_length', 4000));
        $sql = mb_substr($rawSql, 0, $maxSqlLength);

        return array(
            'query' => $sql,
            'action' => $this->resolveAction(),
            'query_type' => $queryVerb,
            'query_time' => $query->time,
            'query_at' => Carbon::now(),
            'query_order' => ($this->arrLoggedCountsByConnection[$connectionName] ?? 0) + 1,
            'connection' => $connectionName,
            'is_screen' => app()->runningInConsole() ? 0 : 1,
            'user_id' => $this->resolveUserId(),
            'ip' => request() ? request()->ip() : null,
        );
    }

    private function interpolateSql($sql, array $arrBindings): string
    {
        $interpolatedSql = (string) $sql;
        foreach ($arrBindings as $index => $binding) {
            $isSensitiveBinding = $this->isSensitiveBinding($interpolatedSql, (int) $index);
            $value = $this->quoteBinding($binding, $isSensitiveBinding);
            $interpolatedSql = preg_replace('/\?/', $value, $interpolatedSql, 1) ?: $interpolatedSql;
        }

        return str_replace(array("\r\n", "\n"), ' ', trim($interpolatedSql));
    }

    private function quoteBinding($binding, $isSensitive = false): string
    {
        if ($isSensitive && (bool) config('daito-query-log.mask_sensitive_bindings', true)) {
            return "'" . (string) config('daito-query-log.masked_value', '***') . "'";
        }

        if ($binding === null) {
            return 'null';
        }
        if (is_bool($binding)) {
            return $binding ? '1' : '0';
        }
        if (is_numeric($binding)) {
            return (string) $binding;
        }
        if ($binding instanceof \DateTimeInterface) {
            return "'" . $binding->format('Y-m-d H:i:s') . "'";
        }

        return "'" . str_replace("'", "''", (string) $binding) . "'";
    }

    private function isSensitiveBinding($interpolatedSql, int $bindingIndex): bool
    {
        $arrSensitiveKeywords = (array) config('daito-query-log.sensitive_keywords', array());
        if ($arrSensitiveKeywords === array()) {
            return false;
        }

        $parts = explode('?', (string) $interpolatedSql);
        if (!isset($parts[$bindingIndex])) {
            return false;
        }

        $context = strtolower(substr($parts[$bindingIndex], -120));
        foreach ($arrSensitiveKeywords as $keyword) {
            $keywordText = strtolower((string) $keyword);
            if ($keywordText !== '' && strpos($context, $keywordText) !== false) {
                return true;
            }
        }

        return false;
    }

    private function resolveAction(): string
    {
        if (app()->runningInConsole()) {
            return $this->resolveConsoleCommand();
        }

        $request = request();
        if ($request === null) {
            return 'http';
        }

        return $request->method() . ' ' . $request->fullUrl();
    }

    private function resolveUserId()
    {
        try {
            return Auth::check() ? Auth::id() : null;
        } catch (Throwable $throwable) {
            return null;
        }
    }

    private function shouldSkipCurrentContext(): bool
    {
        if (app()->runningInConsole()) {
            $command = $this->resolveConsoleCommand();
            return $this->matchPatterns($command, (array) config('daito-query-log.skip_command_patterns', array()));
        }

        $request = request();
        if ($request === null) {
            return false;
        }

        $route = $request->route();
        $routeName = is_object($route) && method_exists($route, 'getName') ? (string) $route->getName() : '';
        $routePath = ltrim((string) $request->path(), '/');
        $fullUrl = (string) $request->fullUrl();

        $arrTargets = array($routeName, $routePath, $fullUrl);
        foreach ($arrTargets as $target) {
            if ($this->matchPatterns($target, (array) config('daito-query-log.skip_route_patterns', array()))) {
                return true;
            }
        }

        return false;
    }

    private function resolveConsoleCommand(): string
    {
        $arrArgv = $_SERVER['argv'] ?? array();
        return isset($arrArgv[1]) ? trim((string) $arrArgv[1]) : 'console';
    }

    private function matchPatterns(string $target, array $arrPatterns): bool
    {
        if ($target === '' || $arrPatterns === array()) {
            return false;
        }

        foreach ($arrPatterns as $pattern) {
            $patternText = (string) $pattern;
            if ($patternText !== '' && $this->matchPattern($patternText, $target)) {
                return true;
            }
        }

        return false;
    }

    private function matchPattern(string $pattern, string $target): bool
    {
        if (function_exists('fnmatch')) {
            return fnmatch($pattern, $target);
        }

        $regex = '/^' . str_replace(array('\*', '\?'), array('.*', '.'), preg_quote($pattern, '/')) . '$/i';
        return preg_match($regex, $target) === 1;
    }

    private function isPassedSampling(): bool
    {
        $sampleRate = (float) config('daito-query-log.sample_rate', 100);
        if ($sampleRate >= 100) {
            return true;
        }
        if ($sampleRate <= 0) {
            return false;
        }

        $random = mt_rand(1, 10000) / 100;
        return $random <= $sampleRate;
    }

    private function isInTransaction(Connection $connection): bool
    {
        return method_exists($connection, 'transactionLevel') && $connection->transactionLevel() > 0;
    }

    private function shouldLogInCurrentRuntime(): bool
    {
        if (app()->runningInConsole()) {
            return (bool) config('daito-query-log.log_on_console', false);
        }

        return true;
    }

    private function isEnabled(): bool
    {
        return (bool) config('daito-query-log.enable', false);
    }

    private function registerPublishableResources(): void
    {
        $this->publishes(
            array(
                __DIR__ . '/../config/daito-query-log.php' => config_path('daito-query-log.php'),
            ),
            'daito-query-log-config'
        );

        $this->publishes(
            array(
                __DIR__ . '/../database/migrations/2026_02_20_000000_daito_create_log_queries_table.php'
                    => database_path('migrations/' . date('Y_m_d_His') . '_daito_create_log_queries_table.php'),
            ),
            'daito-query-log-migrations'
        );
    }
}
