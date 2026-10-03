<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\FakeBridge;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        // Pin the bridge to its in-test fake so secure-storage calls
        // resolve deterministically instead of reaching the live Jump
        // bridge over TCP (which would share real device storage across
        // tests when previewing on device).
        FakeBridge::enable();
    }

    protected function tearDown(): void
    {
        AsyncTask::clearFake();
        FakeBridge::disable();

        parent::tearDown();
    }
}
