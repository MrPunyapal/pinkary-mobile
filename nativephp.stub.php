<?php

/**
 * Static-analysis declarations for the NativePHP routing macros.
 *
 * Route::native() and Route::nativeGroup() are runtime macros registered in
 * NativeServiceProvider::boot(), so PHPStan resolves them through Macroable's
 * __call and returns mixed. Every chained call then fails, which is 16 errors in
 * routes/web.php.
 *
 * Why this file exists, and why it is shaped this way:
 *
 * vendor/nativephp/mobile/_ide_helper.php covers this for IDEs but re-declares
 * Illuminate\Routing\Route as an empty class, which shadows the real one, so a
 * resolved native() then fails on ->name(). It is also IDE-only by its own
 * header. Larastan does support macros, but MacroMethodsClassReflectionExtension
 * derives a macro's type from its closure signature, and the registration is
 * `function (string $uri, string $componentClass)` with no return type.
 *
 * stubFiles cannot be used: Larastan already ships a stub declaring
 * Illuminate\Routing\Router, so PHPStan reports class.duplicate and drops this.
 *
 * scanFiles works, but it makes this declaration authoritative for
 * Illuminate\Routing\Router, which means every Router method used through the
 * facade must be declared here or it becomes "undefined method". This app uses
 * exactly one: get(). If a future route file calls another Router method, add it
 * to this class in the same change, or PHPStan will fail on the new call.
 */

declare(strict_types=1);

namespace Illuminate\Routing;

use Closure;

class Router
{
    /**
     * @param  string  $uri
     * @param  callable|string|array<array-key, mixed>|null  $action
     */
    public function get($uri, $action = null): Route
    {
    }

    /**
     * @param  string  $uri
     * @param  class-string  $componentClass
     */
    public function native($uri, $componentClass): Route
    {
    }

    /**
     * @param  string  $layout
     * @param  Closure  $routes
     */
    public function nativeGroup($layout, Closure $routes): void
    {
    }
}
