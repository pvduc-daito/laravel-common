<?php

namespace Daito\Lib\DaitoQueryLog\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class DaitoSaveQueryLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $arrQueries;

    public function __construct(array $arrQueries)
    {
        $this->arrQueries = $arrQueries;
    }

    public function handle(): void
    {
        if ($this->arrQueries === array()) {
            return;
        }

        DB::connection(config('daito-query-log.connection', 'query_log'))
            ->table(config('daito-query-log.table', 'log_queries'))
            ->insert($this->arrQueries);
    }
}
