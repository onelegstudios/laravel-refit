<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Contracts;

use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Project\Project;

/**
 * One reversible-on-paper change to the project.
 *
 * Actions are built during planning and only executed once the user confirms, so
 * `describe()` has to stand on its own as the thing the user is agreeing to.
 */
interface Action
{
    /**
     * A single line for the confirmation preview.
     */
    public function describe(): string;

    public function apply(Project $project, Report $report): void;
}
