<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Contracts;

use Onelegstudios\Refit\Plan\Plan;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Project\Project;
use Onelegstudios\Refit\Refit;
use Onelegstudios\Refit\Tasks\TaskGroup;

/**
 * An optional job the user can choose to run.
 *
 * Tasks are the open-ended half of refit, which is why this is an interface on
 * day one: a package can register its own through the {@see Refit}
 * registry without refit knowing anything about it.
 */
interface Task
{
    /**
     * Stable identifier, used by `--answers` and in tests.
     */
    public function key(): string;

    public function group(): TaskGroup;

    public function label(): string;

    public function hint(): string;

    /**
     * Whether this task makes sense for the detected project.
     *
     * A task that cannot apply is never offered, so the user is not asked to
     * choose between jobs that would silently do nothing.
     */
    public function appliesTo(Project $project): bool;

    public function contribute(Plan $plan, Project $project, Report $report): void;
}
