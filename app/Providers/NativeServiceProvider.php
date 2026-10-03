<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MrPunyapal\Impressions\Providers\ImpressionsServiceProvider;
use Native\Mobile\Providers\CameraServiceProvider;
use Native\Mobile\UI\NativeUIServiceProvider;

class NativeServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}

    /**
     * @return array<int, class-string<ServiceProvider>>
     */
    public function plugins(): array
    {
        return [
            NativeUIServiceProvider::class,
            ImpressionsServiceProvider::class,

            // The camera facade needs the picker plugin's native bridge implementation.
            CameraServiceProvider::class,
        ];
    }
}
