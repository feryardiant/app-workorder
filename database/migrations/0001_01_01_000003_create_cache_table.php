<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function shouldRun(): bool
    {
        return config('cache.default') === 'database';
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $cache = config('cache.stores.database');

        Schema::connection($cache['connection'])->create($cache['table'], function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::connection($cache['lock_connection'])->create($cache['lock_table'], function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $cache = config('cache.stores.database');

        Schema::connection($cache['connection'])->dropIfExists($cache['table']);
        Schema::connection($cache['lock_connection'])->dropIfExists($cache['lock_table']);
    }
};
