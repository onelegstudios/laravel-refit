<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Tests;

use Onelegstudios\Refit\RefitServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            RefitServiceProvider::class,
        ];
    }
}
