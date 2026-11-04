<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($connection = $this->resolveDatabaseQueueConnection()) {
            $queue = config('queue.connections.'.$connection);

            Schema::connection($queue['connection'])->create($queue['table'], function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedSmallInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        $batching = config('queue.batching');

        Schema::connection($batching['database'])->create($batching['table'], function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        $failed = config('queue.failed');

        Schema::connection($failed['database'])->create($failed['table'], function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('connection');
            $table->string('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();

            $table->index(['connection', 'queue', 'failed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($connection = $this->resolveDatabaseQueueConnection()) {
            $queue = config('queue.connections.'.$connection);
            Schema::connection($queue['connection'])->dropIfExists($queue['table']);
        }

        $batching = config('queue.batching');
        Schema::connection($batching['database'])->dropIfExists($batching['table']);

        $failed = config('queue.failed');
        Schema::connection($failed['database'])->dropIfExists($failed['table']);
    }

    private function resolveDatabaseQueueConnection(): false|string
    {
        $default = config('queue.default');
        $connections = config('queue.connections', []);
        $connection = $connections[$default] ?? false;

        if (! $connection) {
            return false;
        }

        if ($connection['driver'] === 'failover') {
            foreach ($connection['connections'] as $fallback) {
                if (($connections[$fallback]['driver'] ?? null) === 'database') {
                    return $fallback;
                }
            }
        }

        return $connection['driver'] === 'database' ? $default : false;
    }
};
