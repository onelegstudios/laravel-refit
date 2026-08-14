<?php

declare(strict_types=1);

use Onelegstudios\Refit\Tasks\KeepOneLayout;
use Onelegstudios\Refit\Tasks\MoveToastsToTop;
use Onelegstudios\Refit\Tasks\NamespaceComponents;
use Onelegstudios\Refit\Tasks\PromotePartialsToComponents;
use Onelegstudios\Refit\Tasks\RemoveFluxProSource;

return [

    /*
    |--------------------------------------------------------------------------
    | Tasks
    |--------------------------------------------------------------------------
    |
    | The optional tasks `php artisan refit` offers once the icon set is chosen.
    | Each is resolved from the container, so a task may type-hint its own
    | dependencies. Add your own here, or register them from a service provider
    | with `Refit::task(new YourTask)`.
    |
    | Only tasks whose `appliesTo()` returns true for the detected starter kit
    | are shown, so listing one that does not fit is harmless.
    |
    */

    'tasks' => [
        PromotePartialsToComponents::class,
        NamespaceComponents::class,
        MoveToastsToTop::class,
        KeepOneLayout::class,
        RemoveFluxProSource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notes file
    |--------------------------------------------------------------------------
    |
    | Where refit writes the run report when it has anything to flag — icons it
    | could not translate, files it declined to touch. Set to null to keep the
    | report on screen only.
    |
    */

    'notes' => 'REFIT-NOTES.md',

];
