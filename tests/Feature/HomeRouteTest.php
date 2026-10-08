<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

it('renders the home screen over http', function (): void {
    config(['services.pinkary.url' => 'https://pinkary.test']);
    Http::fake([
        'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
    ]);

    $this->get('/')->assertStatus(200);
});
