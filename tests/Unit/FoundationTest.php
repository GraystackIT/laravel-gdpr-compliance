<?php

declare(strict_types=1);

use GraystackIt\Gdpr\GdprServiceProvider;

it('boots the service provider', function () {
    expect(app()->getProvider(GdprServiceProvider::class))
        ->toBeInstanceOf(GdprServiceProvider::class);
});
