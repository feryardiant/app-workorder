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
            $id = (string) $id;
            $handle = 'user'.$id;

            if (\strlen($handle) > 15) {
                $handle = 'h'.self::toBase36($id);
            }

            DB::table('users')->where('id', $id)->update(['handle' => $handle]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('handle', 15)->nullable(false)->change();
        });
    }

    /**
     * Convert a decimal string to base 36 without losing precision.
     */
    private static function toBase36(string $value): string
    {
        $alphabet = '0123456789abcdefghijklmnopqrstuvwxyz';
        $result = '';

        while ($value !== '' && $value !== '0') {
            $remainder = 0;
            $quotient = '';

            foreach (str_split($value) as $digit) {
                $number = $remainder * 10 + (int) $digit;
                $quotient .= intdiv($number, 36);
                $remainder = $number % 36;
            }

            $result = $alphabet[$remainder].$result;
            $value = ltrim($quotient, '0');
        }

        return $result === '' ? '0' : $result;
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
