<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\RequestStatus;
use GraystackIt\Gdpr\Enums\RequestType;
use GraystackIt\Gdpr\Models\GdprRequest;
use GraystackIt\Gdpr\Support\PersonalDataExporter;
use Illuminate\Support\Facades\Storage;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

it('writes a JSON export with data grouped by model class', function () {
    Storage::fake('local');

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'phone' => '+43']);
    Order::create(['user_id' => $user->id, 'billing_email' => 'ada@example.com', 'shipping_address' => 'Main St 1', 'total' => 29.90]);

    $request = GdprRequest::create([
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'type' => RequestType::Export,
        'status' => RequestStatus::Pending,
        'requested_at' => now(),
    ]);

    $exporter = app(PersonalDataExporter::class);
    $result = $exporter->export($user, $request);

    Storage::disk('local')->assertExists($result['path']);

    $content = json_decode(Storage::disk('local')->get($result['path']), true);

    expect($content['subject']['type'])->toBe(User::class)
        ->and((int) $content['subject']['id'])->toBe((int) $user->id)
        ->and($content['data'])->toHaveKey(User::class)
        ->and($content['data'])->toHaveKey(Order::class);

    $userRow = $content['data'][User::class][0];
    expect($userRow)->toHaveKey('name')
        ->and($userRow)->toHaveKey('email')
        ->and($userRow)->not->toHaveKey('password'); // notExportable

    $orderRow = $content['data'][Order::class][0];
    expect($orderRow)->toHaveKey('billing_email')
        ->and($orderRow)->toHaveKey('total');
});
