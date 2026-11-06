<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the users migration backfills handles for existing users', function () {
    $originalConnection = config('database.default');
    $database = tempnam(sys_get_temp_dir(), 'handle-backfill-');

    config([
        'database.connections.handle_backfill' => [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
        'database.default' => 'handle_backfill',
    ]);

    DB::purge('handle_backfill');

    try {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
        });

        DB::table('users')->insert([
            ['id' => '1'],
            ['id' => '2'],
            ['id' => '12345'],
            ['id' => '2226305911109'],
            ['id' => '18446744073709551614'],
            ['id' => '18446744073709551615'],
        ]);

        $migration = require database_path('migrations/2026_11_06_015459_add_preferences_columns_to_users_table.php');
        $migration->up();

        $handles = DB::table('users')->pluck('handle', 'id');

        expect($handles['1'])->toBe('user1')
            ->and($handles['2'])->toBe('user2')
            ->and($handles['12345'])->toBe('user12345')
            ->and($handles['2226305911109'])->toStartWith('h')
            ->and($handles['2226305911109'])->not->toBe($handles['12345'])
            ->and($handles['18446744073709551614'])->not->toBe($handles['18446744073709551615'])
            ->and($handles->unique())->toHaveCount(6);

        $handles->each(fn (string $handle) => expect($handle)->toMatch('/^[a-z0-9]{4,15}$/'));

        expect(fn () => DB::table('users')->insert(['id' => '3']))
            ->toThrow(QueryException::class);

        expect(fn () => DB::table('users')->insert(['id' => '4', 'handle' => 'user1']))
            ->toThrow(QueryException::class);
    } finally {
        DB::disconnect('handle_backfill');
        config(['database.default' => $originalConnection]);
        @unlink($database);
    }
});
