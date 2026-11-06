<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (User::query()->count() > 0) {
            return;
        }

        if (app()->environment('local', 'testing')) {
            $this->localUsers();
        } else {
            $this->initialUsers();
        }
    }

    private function localUsers(): void
    {
        User::factory()->create([
            'email' => 'owner@example.com',
            'handle' => 'owner',
            'role' => Role::Owner,
        ]);

        User::factory()->create([
            'email' => 'admin@example.com',
            'handle' => 'admin',
            'role' => Role::Admin,
        ]);

        User::factory(3)->state(new Sequence(fn (Sequence $sequence) => [
            'email' => \sprintf('member-%d@example.com', $sequence->index + 1),
            'handle' => \sprintf('member%d', $sequence->index + 1),
        ]))->create(['role' => Role::Member]);
    }

    private function initialUsers(): void
    {
        (new User)->forceFill([
            'name' => \sprintf('%s Owner', config('app.name')),
            'email' => 'owner@example.com',
            'handle' => 'owner',
            'role' => Role::Owner,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ])->save();
    }
}
