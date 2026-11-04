<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('local')) {
            $this->localData();
        }
    }

    private function localData(): void
    {
        if (User::query()->count() > 0) {
            return;
        }

        User::factory()->create([
            'email' => 'owner@example.com',
            'role' => Role::Owner,
        ]);

        User::factory()->create([
            'email' => 'admin@example.com',
            'role' => Role::Admin,
        ]);

        User::factory()->create([
            'email' => 'member@example.com',
            'role' => Role::Member,
        ]);

        User::factory(2)->create(['role' => Role::Member]);
    }
}
