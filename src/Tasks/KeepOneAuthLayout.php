<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Tasks;

use Onelegstudios\Refit\Contracts\Task;
use Onelegstudios\Refit\Plan\Actions\DeleteFile;
use Onelegstudios\Refit\Plan\Plan;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Plan\Stage;
use Onelegstudios\Refit\Project\Project;

/**
 * Drop the auth layouts the application does not use.
 *
 * The kit ships three — card, simple and split — and `layouts/auth.blade.php`
 * delegates to exactly one of them. The other two are dead weight from the first
 * commit onwards.
 *
 * Which one survives is read from the delegating layout rather than asked, so the
 * task cannot pick a different answer than the application already has.
 */
final class KeepOneAuthLayout implements Task
{
    private const string DIRECTORY = 'resources/views/layouts/auth';

    private const string DELEGATE = 'resources/views/layouts/auth.blade.php';

    public function key(): string
    {
        return 'single-auth-layout';
    }

    public function group(): TaskGroup
    {
        return TaskGroup::Cleanup;
    }

    public function label(): string
    {
        return 'Delete the unused auth layouts';
    }

    public function hint(): string
    {
        return 'Keeps only the auth layout that layouts/auth.blade.php actually renders';
    }

    public function appliesTo(Project $project): bool
    {
        return $this->unused($project) !== [];
    }

    public function contribute(Plan $plan, Project $project, Report $report): void
    {
        $keep = $this->inUse($project);

        foreach ($this->unused($project) as $name) {
            $plan->add(Stage::Move, new DeleteFile(
                sprintf('%s/%s.blade.php', self::DIRECTORY, $name),
                sprintf('delete %s/%s.blade.php (keeping "%s")', self::DIRECTORY, $name, $keep),
            ));
        }
    }

    /**
     * The layout variant `layouts/auth.blade.php` renders.
     */
    private function inUse(Project $project): ?string
    {
        if (! $project->exists(self::DELEGATE)) {
            return null;
        }

        if (preg_match('/<x-layouts::auth\.([a-z0-9-]+)/', $project->get(self::DELEGATE), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    private function unused(Project $project): array
    {
        $keep = $this->inUse($project);

        if ($keep === null) {
            return [];
        }

        $matches = glob($project->path(self::DIRECTORY).'/*.blade.php');

        if ($matches === false) {
            return [];
        }

        $unused = [];

        foreach ($matches as $path) {
            $name = basename($path, '.blade.php');

            if ($name !== $keep) {
                $unused[] = $name;
            }
        }

        return $unused;
    }
}
