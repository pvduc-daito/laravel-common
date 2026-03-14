<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $connection = config('daito-query-log.connection', 'query_log');
        $table = config('daito-query-log.table', 'log_queries');

        Schema::connection($connection)->create($table, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('action', 512)->nullable();
            $table->longText('query');
            $table->string('query_type', 32);
            $table->decimal('query_time', 10, 3)->default(0);
            $table->dateTime('query_at');
            $table->unsignedInteger('query_order')->default(0);
            $table->string('connection', 64)->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('user_id', 64)->nullable();
            $table->boolean('is_screen')->default(false);

            $table->index('query_at', 'idx_log_queries_query_at');
            $table->index('query_type', 'idx_log_queries_query_type');
            $table->index('connection', 'idx_log_queries_connection');
            $table->index('user_id', 'idx_log_queries_user_id');
            $table->index(array('query_at', 'query_type'), 'idx_log_queries_at_type');
            $table->index(array('connection', 'query_at'), 'idx_log_queries_connection_at');
        });
    }

    public function down(): void
    {
        $connection = config('daito-query-log.connection', 'query_log');
        $table = config('daito-query-log.table', 'log_queries');

        Schema::connection($connection)->dropIfExists($table);
    }
};
