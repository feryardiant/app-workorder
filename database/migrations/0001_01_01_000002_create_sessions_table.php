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
        return config('session.driver') === 'database';
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $session = config('session');

        Schema::connection($session['connection'])->create($session['table'], function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()
                ->references('id')
                ->on($this->usersTable())
                ->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $session = config('session');

        Schema::connection($session['connection'])->dropIfExists($session['table']);
    }

    /**
     * Resolve the `users` table used by the foreign key.
     *
     * PostgreSQL keeps the application tables in a separate schema, so the
     * reference has to be schema-qualified. Other drivers must keep the bare
     * table name, because a `schema.table` reference is read as a
     * `database.table` reference for them.
     */
    protected function usersTable(): string
    {
        $connection = config('session.connection') ?? config('database.default');

        return config("database.connections.{$connection}.driver") === 'pgsql'
            ? config('database.schemas.public').'.users'
            : 'users';
    }
};
