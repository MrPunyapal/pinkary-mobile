<?php

declare(strict_types=1);

arch('pinkary api is the only http boundary')
    ->expect(['App\NativeComponents', 'App\NativeLayout', 'App\Support', 'App\Providers', 'App\Models'])
    ->not->toUse([
        Illuminate\Support\Facades\Http::class,
    ]);

arch('api host is never hardcoded outside config')
    ->expect(['App\NativeComponents', 'App\NativeLayout', 'App\Support', 'App\Services'])
    ->not->toContain([
        'pinkary.com',
        'pinkary.test',
    ]);
