<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Support\SubjectRecordResolver;
use Workbench\App\Models\Address;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

it('counts rows for each registered model that scopes to the subject', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);
    Order::create(['user_id' => $user->id, 'billing_email' => 'ada@example.com', 'total' => 29.90]);
    Order::create(['user_id' => $user->id, 'billing_email' => 'ada@example.com', 'total' => 49.00]);
    Address::create(['user_id' => $user->id, 'line1' => 'Main St 1', 'city' => 'Vienna']);

    // Unrelated data that must NOT be counted
    $other = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);
    Order::create(['user_id' => $other->id, 'billing_email' => 'bob@example.com', 'total' => 10.00]);

    $resolver = app(SubjectRecordResolver::class);
    $counts = $resolver->countsFor($user);

    expect($counts[User::class])->toBe(1)
        ->and($counts[Order::class])->toBe(2)
        ->and($counts[Address::class])->toBe(1);
});
