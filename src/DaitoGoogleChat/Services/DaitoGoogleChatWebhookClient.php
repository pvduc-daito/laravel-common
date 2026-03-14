<?php

namespace Daito\Lib\DaitoGoogleChat\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DaitoGoogleChatWebhookClient
{
    public function send(array $arrPayload, $webhookUrl = null): array
    {
        if (!(bool) config('daito-google-chat.enabled', false)) {
            return array(
                'success' => 0,
                'status' => 'disabled',
                'message' => 'Daito Google Chat notification is disabled.',
            );
        }

        $resolvedWebhookUrl = $this->resolveWebhookUrl($webhookUrl);
        $this->assertWebhookUrlIsAllowed($resolvedWebhookUrl);

        $response = Http::withHeaders(
            array(
                'Accept' => 'application/json',
            )
        )
            ->asJson()
            ->withOptions(
                array(
                    'connect_timeout' => (int) config('daito-google-chat.connect_timeout_seconds', 3),
                    'verify' => (bool) config('daito-google-chat.verify_ssl', true),
                )
            )
            ->timeout((int) config('daito-google-chat.timeout_seconds', 5))
            ->retry(
                (int) config('daito-google-chat.retry_times', 1),
                (int) config('daito-google-chat.retry_sleep_ms', 200)
            )
            ->post($resolvedWebhookUrl, $arrPayload);

        if ($response->successful()) {
            return array(
                'success' => 1,
                'status' => 'sent',
                'http_code' => $response->status(),
            );
        }

        $arrError = array(
            'success' => 0,
            'status' => 'failed',
            'http_code' => $response->status(),
            'message' => trim((string) $response->body()),
        );

        Log::warning('DaitoGoogleChat send failed.', $arrError);

        if ((bool) config('daito-google-chat.throw_on_error', false)) {
            throw new RuntimeException('DaitoGoogleChat send failed: HTTP ' . (string) $response->status());
        }

        return $arrError;
    }

    private function resolveWebhookUrl($webhookUrl): string
    {
        $resolvedWebhookUrl = trim((string) ($webhookUrl ?: config('daito-google-chat.default_webhook_url', '')));
        if ($resolvedWebhookUrl === '') {
            throw new RuntimeException('Google Chat webhook URL is empty.');
        }

        return $resolvedWebhookUrl;
    }

    private function assertWebhookUrlIsAllowed(string $webhookUrl): void
    {
        if (!(bool) config('daito-google-chat.validate_webhook_host', true)) {
            return;
        }

        $host = strtolower((string) parse_url($webhookUrl, PHP_URL_HOST));
        if ($host === '') {
            throw new RuntimeException('Google Chat webhook URL is invalid.');
        }

        $arrAllowedWebhookHosts = array_map('strtolower', (array) config('daito-google-chat.allowed_webhook_hosts', array()));
        if (!in_array($host, $arrAllowedWebhookHosts, true)) {
            throw new RuntimeException('Google Chat webhook host is not allowed: ' . $host);
        }
    }
}
