<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan\Actions;

use Onelegstudios\Refit\Contracts\Action;
use Onelegstudios\Refit\Plan\BladeGuard;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Project\Project;

/**
 * An action that rewrites every Blade file in the project.
 *
 * Sweeps run in the reconcile stage, over the settled tree, so they see files
 * that earlier stages created or moved.
 *
 * Each file is guarded individually: if a rewrite would leave the Blade less
 * balanced than it found it, that one file is left alone and the problem is
 * reported. Skipping beats aborting here — a half-applied run is harder to reason
 * about than one bad file, and the clean-tree precondition means `git checkout .`
 * is always available as the real undo.
 */
abstract class BladeSweep implements Action
{
    private BladeGuard $guard;

    abstract protected function transform(string $source, Project $project, Report $report): string;

    public function apply(Project $project, Report $report): void
    {
        $guard = $this->guard ??= new BladeGuard;

        foreach ($project->blades() as $path) {
            $source = $project->get($path);

            if ($source === '') {
                continue;
            }

            $rewritten = $this->transform($source, $project, $report);

            if ($rewritten === $source) {
                continue;
            }

            $problems = $guard->check($source, $rewritten);

            if ($problems !== []) {
                $report->warn(sprintf(
                    'Left %s alone — "%s" would have broken it: %s',
                    $path,
                    $this->describe(),
                    implode('; ', $problems),
                ));

                continue;
            }

            file_put_contents($project->path($path), $rewritten);

            $report->changed($path);
        }
    }
}
