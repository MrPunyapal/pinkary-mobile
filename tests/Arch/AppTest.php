<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->security();

arch()->preset()->laravel()
    ->ignoring([
        'App\Icons',
    ]);

arch('strict types')
    ->expect('App')
    ->toUseStrictTypes()
    ->ignoring([
        'App\Icons',
    ]);

arch('avoid open for extension')
    ->expect('App')
    ->classes()
    ->toBeFinal()
    ->ignoring([
        'App\Icons',
    ]);

arch('ensure no extends')
    ->expect('App')
    ->classes()
    ->not->toBeAbstract()
    ->ignoring([
        'App\Icons',
    ]);

/*
 * Deliberately not enforced here, unlike the web app:
 *
 * toBeReadonly() — a NativePHP component's public properties are the state that native elements
 *   mutate, and app/Support classes are static. Neither can be readonly.
 *
 * toExtendNothing() — every screen must extend NativeComponent, the layout NativeLayout, and the
 *   User model extends Authenticatable. The framework is the reason for the inheritance.
 *
 * toHaveMethodsDocumented() — the documented target, but ~38 undocumented methods on PinkaryApi
 *   alone make this a large doc pass rather than a rule we can switch on now.
 */
