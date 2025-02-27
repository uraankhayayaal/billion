<?php

declare(strict_types=1);

namespace Tests;

use Laravel\Lumen\Application;
use Laravel\Lumen\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     *
     * @return \Laravel\Lumen\Application
     */
    public function createApplication(): Application
    {
        return require __DIR__ . '/../bootstrap/app.php';
    }
}
