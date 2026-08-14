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
 * Drop the layouts in one family the application does not use.
 *
 * The kit builds both of its shells the same way: `layouts/<family>.blade.php`
 * renders exactly one of the variants sitting in `layouts/<family>/`, and the
 * rest are dead weight from the first commit onwards.
 *
 * Which one survives is read from the delegating layout rather than asked, so the
 * task cannot pick a different answer than the application already has.
 */
abstract class KeepOneLayout implements Task
{
    /**
     * The family name — both the delegating layout's filename and the folder of
     * variants beside it.
     */
    abstract protected function family(): string;

    public function key(): string
    {
        return sprintf('single-%s-layout', $this->family());
    }

    public function group(): TaskGroup
    {
        return TaskGroup::Cleanup;
    }

    public function label(): string
    {
        return sprintf('Delete the unused %s layouts', $this->family());
    }

    public function hint(): string
    {
        return sprintf('Keeps only the layout that %s actually renders', $this->delegate());
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
                sprintf('%s/%s.blade.php', $this->directory(), $name),
                sprintf('delete %s/%s.blade.php (keeping "%s")', $this->directory(), $name, $keep),
            ));
        }
    }

    /**
     * The folder holding the family's variants.
     */
    private function directory(): string
    {
        return sprintf('resources/views/layouts/%s', $this->family());
    }

    /**
     * The layout every view names, which renders one variant and nothing else.
     */
    private function delegate(): string
    {
        return sprintf('resources/views/layouts/%s.blade.php', $this->family());
    }

    /**
     * The variant the delegating layout renders.
     */
    private function inUse(Project $project): ?string
    {
        if (! $project->exists($this->delegate())) {
            return null;
        }

        $pattern = sprintf('/<x-layouts::%s\.([a-z0-9-]+)/', preg_quote($this->family(), '/'));

        if (preg_match($pattern, $project->get($this->delegate()), $matches) !== 1) {
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

        $matches = glob($project->path($this->directory()).'/*.blade.php');

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
