<?php

declare(strict_types=1);

use App\Models\User;

test('the database seeder creates users with handles', function () {
    $this->seed();

    expect(User::query()->orderBy('id')->pluck('handle')->all())
        ->toBe(['owner', 'admin', 'member1', 'member2', 'member3'])
        ->and(User::query()->whereNull('handle')->exists())->toBeFalse();
});
