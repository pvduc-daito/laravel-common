<?php

namespace Daito\Lib\DaitoGoogleChat\Jobs;

use Daito\Lib\DaitoGoogleChat\Middleware\DaitoGoogleChatRateLimitMiddleware;
use Daito\Lib\DaitoGoogleChat\Services\DaitoGoogleChatWebhookClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DaitoGoogleChatWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $arrPayload;
    protected $webhookUrl;

    public $tries;
    public $backoff;

    public function __construct(array $arrPayload, $webhookUrl = null)
    {
        $this->arrPayload = $arrPayload;
        $this->webhookUrl = $webhookUrl !== null ? (string) $webhookUrl : null;
        $this->tries = max(1, (int) config('daito-google-chat.queue_tries', 3));
        $this->backoff = max(1, (int) config('daito-google-chat.queue_backoff_seconds', 10));
    }

    public function handle(DaitoGoogleChatWebhookClient $client): void
    {
        $client->send($this->arrPayload, $this->webhookUrl);
    }

    public function middleware(): array
    {
        if (!(bool) config('daito-google-chat.rate_limit_enabled', true)) {
            return array();
        }

        return array(
            new DaitoGoogleChatRateLimitMiddleware(),
        );
    }
}
