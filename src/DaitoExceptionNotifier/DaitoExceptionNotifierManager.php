<?php

namespace Daito\Lib\DaitoExceptionNotifier;

use Daito\Lib\DaitoGoogleChat\DaitoGoogleChatManager;
use Throwable;

class DaitoExceptionNotifierManager
{
    /**
     * Guard re-entrant notify in same process.
     *
     * @var bool
     */
    private static $isNotifying = false;

    /**
     * Lightweight per-process cooldown by fingerprint.
     *
     * @var array<string, float>
     */
    private static $arrFingerprintCooldowns = array();

    /**
     * @var \Daito\Lib\DaitoGoogleChat\DaitoGoogleChatManager
     */
    private $daitoGoogleChatManager;

    public function __construct(DaitoGoogleChatManager $daitoGoogleChatManager)
    {
        $this->daitoGoogleChatManager = $daitoGoogleChatManager;
    }

    public function send(Throwable $throwable, array $arrContext = array(), $webhookUrl = null): array
    {
        if (!(bool) config('daito-exception-notifier.enabled', true)) {
            return array(
                'success' => 0,
                'status' => 'disabled',
            );
        }

        return $this->daitoGoogleChatManager->sendCardV2(
            $this->buildCardV2($throwable, $arrContext),
            $webhookUrl
        );
    }

    public function queue(Throwable $throwable, array $arrContext = array(), $webhookUrl = null): void
    {
        if (!(bool) config('daito-exception-notifier.enabled', true)) {
            return;
        }

        $this->daitoGoogleChatManager->queueCardV2(
            $this->buildCardV2($throwable, $arrContext),
            $webhookUrl
        );
    }

    public function notify(Throwable $throwable, array $arrContext = array(), $webhookUrl = null)
    {
        if (!(bool) config('daito-exception-notifier.enabled', true)) {
            return array(
                'success' => 0,
                'status' => 'disabled',
            );
        }

        if ($this->shouldBlockByLoopGuard($throwable, $arrContext)) {
            return array(
                'success' => 0,
                'status' => 'blocked_loop_guard',
            );
        }

        $mode = strtolower((string) config('daito-exception-notifier.send_mode', 'queue'));
        self::$isNotifying = true;
        try {
            if ($mode === 'sync' || $mode === 'immediate') {
                return $this->send($throwable, $arrContext, $webhookUrl);
            }

            $this->queue($throwable, $arrContext, $webhookUrl);

            return array(
                'success' => 1,
                'status' => 'queued',
            );
        } finally {
            self::$isNotifying = false;
        }
    }

    private function buildCardV2(Throwable $throwable, array $arrContext = array()): array
    {
        $action = isset($arrContext['action']) ? (string) $arrContext['action'] : $this->resolveCurrentAction();
        $arrTraceData = $this->extractTraceData($throwable);
        $arrSummaryWidgets = array(
            $this->makeDecoratedTextWidget('time', date('Y-m-d H:i:s')),
            $this->makeDecoratedTextWidget('action', $action),
            $this->makeCompactFileLineWidget((string) $throwable->getFile(), (int) $throwable->getLine()),
            array(
                'textParagraph' => array(
                    'text' => '<b>message</b><br/><font color="#d93025">'
                        . $this->escapeForGoogleChat(
                            $this->limitText((string) $throwable->getMessage(), (int) config('daito-exception-notifier.message_max_length', 1000))
                        )
                        . '</font>',
                ),
            ),
        );

        // if ((bool) config('daito-exception-notifier.trace_include_first_app_frame', true)
        //     && $arrTraceData['first_app_frame'] !== ''
        // ) {
        //     $arrSummaryWidgets[] = $this->makeDecoratedTextWidget('first_app_frame', $arrTraceData['first_app_frame']);
        // }

        return array(
            'cardId' => 'daito-exception-alert',
            'card' => array(
                'header' => array(
                    'title' => '🚨 ' . (string) config('daito-exception-notifier.card_title', 'Exception Alert'),
                ),
                'sections' => array(
                    array(
                        'header' => 'Summary',
                        'widgets' => $arrSummaryWidgets,
                    ),
                    array(
                        'header' => 'Trace',
                        'widgets' => array(
                            array(
                                'textParagraph' => array(
                                    'text' => $this->escapeForGoogleChat($arrTraceData['trace']),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
    }

    private function extractTraceData(Throwable $throwable): array
    {
        $maxLines = max(1, (int) config('daito-exception-notifier.trace_max_lines', 8));
        $maxLength = (int) config('daito-exception-notifier.trace_max_length', 2500);
        $traceMode = strtolower((string) config('daito-exception-notifier.trace_mode', 'smart'));
        $skipVendor = (bool) config('daito-exception-notifier.trace_skip_vendor', true);
        $arrTrace = (array) $throwable->getTrace();
        $rootDir = function_exists('base_path') ? str_replace('\\', '/', base_path()) : '';
        $appDir = function_exists('base_path') ? str_replace('\\', '/', base_path('app')) : '';
        $arrClassPrefixes = (array) config('daito-exception-notifier.trace_class_prefixes', array('App\\'));
        $arrMappedFrames = array();
        $arrAppFrames = array();
        $arrNonVendorFrames = array();

        foreach ($arrTrace as $index => $arrFrame) {
            $arrFrameInfo = $this->mapTraceFrame($arrFrame, $index);
            $arrMappedFrames[] = $arrFrameInfo;

            if ($this->isAppFrame($arrFrameInfo, $appDir, $arrClassPrefixes)) {
                $arrAppFrames[] = $arrFrameInfo;
            }

            if (!$this->isVendorPath($arrFrameInfo['file'], $rootDir)) {
                $arrNonVendorFrames[] = $arrFrameInfo;
            }
        }

        $arrSelectedFrames = $this->selectTraceFramesByMode(
            $traceMode,
            $arrMappedFrames,
            $arrAppFrames,
            $arrNonVendorFrames,
            $skipVendor,
            $maxLines
        );

        if ($arrSelectedFrames === array()) {
            $arrSelectedFrames = array_slice($arrMappedFrames, 0, $maxLines);
        }

        $arrTraceLines = array();
        foreach ($arrSelectedFrames as $arrFrameInfo) {
            $arrTraceLines[] = $arrFrameInfo['text'];
        }

        $traceText = $this->limitText(implode("\n", $arrTraceLines), $maxLength);
        $firstAppFrame = isset($arrAppFrames[0]) ? $arrAppFrames[0]['text'] : '';

        return array(
            'trace' => $traceText,
            'first_app_frame' => $firstAppFrame,
        );
    }

    private function mapTraceFrame(array $arrFrame, int $index): array
    {
        $file = isset($arrFrame['file']) ? (string) $arrFrame['file'] : '';
        $line = isset($arrFrame['line']) ? (int) $arrFrame['line'] : 0;
        $class = isset($arrFrame['class']) ? (string) $arrFrame['class'] : '';
        $type = isset($arrFrame['type']) ? (string) $arrFrame['type'] : '';
        $function = isset($arrFrame['function']) ? (string) $arrFrame['function'] : 'unknown';
        $text = sprintf('#%d %s%s%s %s:%d', $index, $class, $type, $function, $file !== '' ? $file : '[internal]', $line);

        return array(
            'text' => $text,
            'file' => $file,
            'class' => $class,
        );
    }

    private function isAppFrame(array $arrFrameInfo, string $appDir, array $arrClassPrefixes): bool
    {
        $file = (string) ($arrFrameInfo['file'] ?? '');
        $class = (string) ($arrFrameInfo['class'] ?? '');
        if ($file !== '' && $appDir !== '') {
            $normalizedFile = str_replace('\\', '/', $file);
            if (strpos($normalizedFile, $appDir . '/') === 0 || $normalizedFile === $appDir) {
                return true;
            }
        }

        foreach ($arrClassPrefixes as $prefix) {
            $prefixText = (string) $prefix;
            if ($prefixText !== '' && strpos($class, $prefixText) === 0) {
                return true;
            }
        }

        return false;
    }

    private function selectTraceFramesByMode(
        string $traceMode,
        array $arrMappedFrames,
        array $arrAppFrames,
        array $arrNonVendorFrames,
        bool $skipVendor,
        int $maxLines
    ): array {
        if ($traceMode === 'app_only') {
            return array_slice($arrAppFrames, 0, $maxLines);
        }

        if ($traceMode === 'no_vendor') {
            return array_slice($arrNonVendorFrames, 0, $maxLines);
        }

        if ($traceMode === 'class_prefix_only') {
            return array_slice($arrAppFrames, 0, $maxLines);
        }

        // smart mode: app frames -> non-vendor frames -> fallback all frames
        if ($arrAppFrames !== array()) {
            return array_slice($arrAppFrames, 0, $maxLines);
        }

        if ($skipVendor && $arrNonVendorFrames !== array()) {
            return array_slice($arrNonVendorFrames, 0, $maxLines);
        }

        return array_slice($arrMappedFrames, 0, $maxLines);
    }

    private function resolveCurrentAction(): string
    {
        if (function_exists('app') && app()->runningInConsole()) {
            $arrArgv = $_SERVER['argv'] ?? array();
            return isset($arrArgv[1]) ? 'cli: ' . trim((string) $arrArgv[1]) : 'cli: console';
        }

        if (!function_exists('request')) {
            return 'unknown';
        }

        $request = request();
        if ($request === null) {
            return 'http';
        }

        return $request->method() . ' ' . $request->fullUrl();
    }

    private function isVendorPath(string $path, string $rootDir): bool
    {
        if ($path === '') {
            return false;
        }

        $normalizedPath = str_replace('\\', '/', $path);
        if ($rootDir === '') {
            return strpos($normalizedPath, '/vendor/') !== false;
        }

        return strpos($normalizedPath, $rootDir . '/vendor/') === 0;
    }

    private function makeDecoratedTextWidget(string $topLabel, string $text): array
    {
        return array(
            'decoratedText' => array(
                'topLabel' => $topLabel,
                'text' => $this->escapeForGoogleChat($text),
                'wrapText' => true,
            ),
        );
    }

    private function makeCompactFileLineWidget(string $filePath, int $line): array
    {
        $displayPath = $this->formatCompactPath($filePath);
        $lineText = $line > 0 ? (string) $line : '?';

        return array(
            'textParagraph' => array(
                'text' => '<b>location</b> ' . $this->escapeForGoogleChat($displayPath) . ':' . $lineText,
            ),
        );
    }

    private function formatCompactPath(string $filePath): string
    {
        if ($filePath === '') {
            return '[unknown]';
        }

        $normalizedPath = str_replace('\\', '/', $filePath);
        if (function_exists('base_path')) {
            $rootDir = str_replace('\\', '/', base_path());
            if ($rootDir !== '' && strpos($normalizedPath, $rootDir . '/') === 0) {
                $normalizedPath = substr($normalizedPath, strlen($rootDir) + 1);
            }
        }

        $arrPathParts = explode('/', $normalizedPath);
        $pathPartCount = count($arrPathParts);
        if ($pathPartCount > 4) {
            $arrPathParts = array_slice($arrPathParts, $pathPartCount - 4);
            $normalizedPath = '.../' . implode('/', $arrPathParts);
        }

        return $normalizedPath;
    }

    private function limitText(string $text, int $maxLength): string
    {
        $maxLength = max(1, $maxLength);
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength) . '...';
    }

    private function escapeForGoogleChat(string $text): string
    {
        return str_replace(
            array('&', '<', '>'),
            array('&amp;', '&lt;', '&gt;'),
            $text
        );
    }

    private function shouldBlockByLoopGuard(Throwable $throwable, array $arrContext = array()): bool
    {
        if (!(bool) config('daito-exception-notifier.loop_guard_enabled', true)) {
            return false;
        }

        if (self::$isNotifying) {
            return true;
        }

        if ((bool) config('daito-exception-notifier.loop_guard_skip_if_notifier_in_trace', true)
            && $this->isNotifierRelatedThrowable($throwable)
        ) {
            return true;
        }

        $ttlSeconds = max(1, (int) config('daito-exception-notifier.loop_guard_ttl_seconds', 30));
        $fingerprint = $this->buildExceptionFingerprint($throwable, $arrContext);
        if ($this->isProcessCooldownActive($fingerprint)) {
            return true;
        }

        $this->setProcessCooldown($fingerprint, $ttlSeconds);

        if (!(bool) config('daito-exception-notifier.loop_guard_use_cache', true)) {
            return false;
        }

        if (!function_exists('cache')) {
            return false;
        }

        $cacheKeyPrefix = (string) config('daito-exception-notifier.loop_guard_cache_prefix', 'daito-exception-notifier:loop');
        $cacheKey = $cacheKeyPrefix . ':' . $fingerprint;

        try {
            return cache()->add($cacheKey, 1, $ttlSeconds) === false;
        } catch (Throwable $throwable) {
            return false;
        }
    }

    private function isNotifierRelatedThrowable(Throwable $throwable): bool
    {
        $traceText = $throwable->getTraceAsString();
        if (strpos($traceText, 'Daito\\Lib\\DaitoExceptionNotifier\\') !== false) {
            return true;
        }

        return strpos($traceText, 'Daito\\Lib\\DaitoGoogleChat\\') !== false;
    }

    private function buildExceptionFingerprint(Throwable $throwable, array $arrContext = array()): string
    {
        $action = isset($arrContext['action']) ? (string) $arrContext['action'] : $this->resolveCurrentAction();
        $text = implode('|', array(
            get_class($throwable),
            (string) $throwable->getFile(),
            (string) $throwable->getLine(),
            (string) $throwable->getMessage(),
            $action,
        ));

        return sha1($text);
    }

    private function isProcessCooldownActive(string $fingerprint): bool
    {
        $expiresAt = self::$arrFingerprintCooldowns[$fingerprint] ?? 0.0;
        return $expiresAt > microtime(true);
    }

    private function setProcessCooldown(string $fingerprint, int $ttlSeconds): void
    {
        self::$arrFingerprintCooldowns[$fingerprint] = microtime(true) + max(1, $ttlSeconds);
    }
}
