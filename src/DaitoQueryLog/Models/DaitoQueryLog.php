<?php

namespace Daito\Lib\DaitoQueryLog\Models;

use Illuminate\Database\Eloquent\Model;

class DaitoQueryLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'log_queries';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = array(
        'action',
        'query',
        'query_type',
        'query_time',
        'query_at',
        'query_order',
        'connection',
        'ip',
        'user_id',
        'is_screen',
    );
    protected $connection = 'query_log';

    public function __construct(array $arrAttributes = array())
    {
        parent::__construct($arrAttributes);

        $this->setConnection(config('daito-query-log.connection', 'query_log'));
        $this->setTable(config('daito-query-log.table', 'log_queries'));
    }
}
