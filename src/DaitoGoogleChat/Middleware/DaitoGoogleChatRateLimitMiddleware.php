<?php

namespace Daito\Lib\DaitoGoogleChat\Middleware;

use Illuminate\Support\Facades\RateLimiter;

class DaitoGoogleChatRateLimitMiddleware
{
    public function handle($job, $next)
    {
        $rateLimitKey = (string) config('daito-google-chat.rate_limit_key', 'daito-google-chat:webhook');
        $maxJobs = max(1, (int) config('daito-google-chat.rate_limit_max_jobs', 20));
        $decaySeconds = max(1, (int) config('daito-google-chat.rate_limit_decay_seconds', 60));

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxJobs)) {
            $job->release($decaySeconds);
            return;
        }

        RateLimiter::hit($rateLimitKey, $decaySeconds);
        return $next($job);
    }
}
