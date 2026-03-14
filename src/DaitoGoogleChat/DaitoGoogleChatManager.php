<?php

namespace Daito\Lib\DaitoGoogleChat;

use Daito\Lib\DaitoGoogleChat\Jobs\DaitoGoogleChatWebhookJob;
use Daito\Lib\DaitoGoogleChat\Services\DaitoGoogleChatWebhookClient;

class DaitoGoogleChatManager
{
    /**
     * @var \Daito\Lib\DaitoGoogleChat\Services\DaitoGoogleChatWebhookClient
     */
    private $client;

    public function __construct(DaitoGoogleChatWebhookClient $client)
    {
        $this->client = $client;
    }

    public function sendText($text, array $arrContext = array(), $webhookUrl = null): array
    {
        return $this->sendPayload(
            array(
                'text' => $this->buildTextContent((string) $text, $arrContext),
            ),
            $webhookUrl
        );
    }

    public function sendPayload(array $arrPayload, $webhookUrl = null): array
    {
        return $this->client->send($arrPayload, $webhookUrl);
    }

    public function sendCardV2(array $arrCardV2, $webhookUrl = null): array
    {
        return $this->sendCardsV2(array($arrCardV2), $webhookUrl);
    }

    public function sendCardsV2(array $arrCardsV2, $webhookUrl = null): array
    {
        return $this->sendPayload(
            array(
                'cardsV2' => array_values($arrCardsV2),
            ),
            $webhookUrl
        );
    }

    public function queueText($text, array $arrContext = array(), $webhookUrl = null): void
    {
        $this->queuePayload(
            array(
                'text' => $this->buildTextContent((string) $text, $arrContext),
            ),
            $webhookUrl
        );
    }

    public function queuePayload(array $arrPayload, $webhookUrl = null): void
    {
        $job = new DaitoGoogleChatWebhookJob($arrPayload, $webhookUrl);
        if (config('daito-google-chat.queue_connection') !== null) {
            $job->onConnection(config('daito-google-chat.queue_connection'));
        }
        if (config('daito-google-chat.queue_name') !== null) {
            $job->onQueue(config('daito-google-chat.queue_name'));
        }

        dispatch($job);
    }

    public function queueCardV2(array $arrCardV2, $webhookUrl = null): void
    {
        $this->queueCardsV2(array($arrCardV2), $webhookUrl);
    }

    public function queueCardsV2(array $arrCardsV2, $webhookUrl = null): void
    {
        $this->queuePayload(
            array(
                'cardsV2' => array_values($arrCardsV2),
            ),
            $webhookUrl
        );
    }

    private function buildTextContent(string $text, array $arrContext = array()): string
    {
        $textContent = trim($text);
        if ($arrContext === array()) {
            return $textContent;
        }

        $arrContextLines = array();
        foreach ($arrContext as $key => $value) {
            $arrContextLines[] = sprintf('%s: %s', (string) $key, $this->normalizeContextValue($value));
        }

        return $textContent . "\n" . implode("\n", $arrContextLines);
    }

    private function normalizeContextValue($value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json !== false ? $json : '[unserializable]';
    }
}
