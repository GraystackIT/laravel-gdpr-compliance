<?php

declare(strict_types=1);

arch('all anonymizers implement the Anonymizer contract')
    ->expect('GraystackIt\Gdpr\Anonymizers')
    ->toImplement('GraystackIt\Gdpr\Contracts\Anonymizer');

arch('models extend Eloquent Model')
    ->expect('GraystackIt\Gdpr\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('jobs implement ShouldQueue')
    ->expect('GraystackIt\Gdpr\Jobs')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

arch('notifications extend Notification')
    ->expect('GraystackIt\Gdpr\Notifications')
    ->toExtend('Illuminate\Notifications\Notification');

arch('middleware classes have a handle method')
    ->expect('GraystackIt\Gdpr\Middleware')
    ->toHaveMethod('handle');

arch('enums are string-backed')
    ->expect('GraystackIt\Gdpr\Enums')
    ->toBeEnums();

arch('commands extend Illuminate Console Command')
    ->expect('GraystackIt\Gdpr\Commands')
    ->toExtend('Illuminate\Console\Command');

arch('no closures in config')
    ->expect('config')
    ->not->toUse('Closure');
