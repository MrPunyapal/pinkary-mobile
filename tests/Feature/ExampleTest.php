<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        config(['services.pinkary.url' => 'https://pinkary.test']);
        Http::fake([
            'https://pinkary.test/api/v1/feed*' => Http::response(['data' => []]),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
