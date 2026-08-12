<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan;

use Onelegstudios\Refit\Contracts\Action;
use Onelegstudios\Refit\Project\Project;

/**
 * Executes a confirmed plan.
 */
final class Applier
{
    /**
     * @param  (callable(Action): void)|null  $onAction  Called before each action runs.
     */
    public function apply(
        Plan $plan,
        Project $project,
        Report $report,
        ?callable $onAction = null,
    ): void {
        foreach ($plan->actions() as $action) {
            if ($onAction !== null) {
                $onAction($action);
            }

            $action->apply($project, $report);
        }
    }
}
