<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\FakeBridge;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');

        // Keep tests isolated from secure storage on a connected device.
        FakeBridge::enable();
    }

    protected function tearDown(): void
    {
        AsyncTask::clearFake();
        FakeBridge::disable();

        parent::tearDown();
    }
}
