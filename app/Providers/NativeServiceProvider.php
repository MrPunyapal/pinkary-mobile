<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Native\Mobile\Providers\CameraServiceProvider;
use Native\Mobile\UI\NativeUIServiceProvider;

class NativeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * The NativePHP plugins to enable.
     *
     * Only plugins listed here will be compiled into your native builds.
     * This is a security measure to prevent transitive dependencies from
     * automatically registering plugins without your explicit consent.
     *
     * @return array<int, class-string<ServiceProvider>>
     */
    public function plugins(): array
    {
        return [
            NativeUIServiceProvider::class,

            // Supplies the Camera.PickMedia bridge function that
            // Native\Mobile\Camera::pickImages() calls. Core ships the PHP
            // facade and the MediaSelected event, but no native
            // implementation behind them, so without this the composer's photo
            // button has no bridge function to reach.
            CameraServiceProvider::class,
        ];
    }
}
