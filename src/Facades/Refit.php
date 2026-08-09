<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Onelegstudios\Refit\Refit
 */
class Refit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Onelegstudios\Refit\Refit::class;
    }
}
