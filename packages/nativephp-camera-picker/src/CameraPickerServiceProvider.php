<?php

declare(strict_types=1);

namespace Pinkary\NativePhp\CameraPicker;

use Illuminate\Support\ServiceProvider;

/**
 * The plugin's only PHP job is to exist.
 *
 * The Kotlin side is what NativePHP needs; this provider is the anchor the
 * plugin allowlist matches on. PluginDiscovery refuses any plugin whose
 * composer provider is not named in App\Providers\NativeServiceProvider::plugins(),
 * so without one this package is silently skipped no matter what its manifest
 * says — a failure that looks exactly like "the bridge function does not exist".
 */
final class CameraPickerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }
}
