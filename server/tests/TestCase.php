<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests use one shared sqlite database; production uses the library connection.
        config([
            'portal.library_connection' => 'sqlite',
            'database.connections.library' => config('database.connections.sqlite'),
            'database.connections.library_source' => config('database.connections.sqlite'),
        ]);
    }
}
