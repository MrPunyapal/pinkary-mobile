<?php

declare(strict_types=1);

use App\Mcp\Servers\SuperStackServer;
use App\Mcp\Tools\AppInfoTool;

it('reports the app identity from config', function (): void {
    config([
        'app.name' => 'Pinkary Mobile',
        'app.env' => 'testing',
        'app.url' => 'https://pinkary.test',
    ]);

    SuperStackServer::tool(AppInfoTool::class)
        ->assertSee('Super Stack')
        ->assertSee('Pinkary Mobile')
        ->assertSee('testing')
        ->assertSee('https://pinkary.test');
});

it('lists the key package versions', function (): void {
    SuperStackServer::tool(AppInfoTool::class)
        ->assertSee('laravel/framework')
        ->assertSee('nativephp/mobile')
        ->assertSee('laravel/mcp');
});

it('reports the running php version', function (): void {
    SuperStackServer::tool(AppInfoTool::class)->assertSee(PHP_VERSION);
});

it('falls back to an empty string when config values are missing', function (): void {
    config(['app.name' => null, 'app.env' => null, 'app.url' => null]);

    SuperStackServer::tool(AppInfoTool::class)->assertDontSee('null');
});
