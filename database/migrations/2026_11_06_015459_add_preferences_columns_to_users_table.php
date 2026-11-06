<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('handle', 15)->nullable()->unique();
            $table->string('locale', 5)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->json('preferences')->nullable();
        });

        foreach (DB::table('users')->pluck('id') as $id) {
            $handle = 'user'.$id;

            if (\strlen($handle) > 15) {
                $handle = 'u'.base_convert((string) $id, 10, 36);
            }

            DB::table('users')->where('id', $id)->update(['handle' => $handle]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('handle', 15)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['handle']);
            $table->dropColumn(['handle', 'locale', 'timezone', 'preferences']);
        });
    }
};
