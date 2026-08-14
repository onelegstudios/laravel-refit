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
 * Drop the layouts the application does not render.
 *
 * The kit builds both of its shells the same way: `layouts/<family>.blade.php`
 * renders exactly one of the variants sitting in `layouts/<family>/`, and the
 * rest are dead weight from the first commit onwards.
 *
 * Which one survives is read from the delegating layout rather than asked, so the
 * task cannot pick a different answer than the application already has.
 *
 * Both families are one task because they are one decision. Every kit ships both,
 * so they are always offered together, and the evidence is the same in each case —
 * a file naming the variant it renders. The plan still lists each deletion, so a
 * single answer hides nothing. Pass your own families to the constructor if your
 * views are laid out differently.
 */
final class KeepOneLayout implements Task
{
    /**
     * @var list<string>
     */
    private const array FAMILIES = ['auth', 'app'];

    /**
     * @param  list<string>  $families  each the delegating layout's filename and the folder of variants beside it
     */
    public function __construct(private readonly array $families = self::FAMILIES) {}

    public function key(): string
    {
        return 'single-layout';
    }

    public function group(): TaskGroup
    {
        return TaskGroup::Cleanup;
    }

    public function label(): string
    {
        return 'Delete the layouts the kit does not render';
    }

    public function hint(): string
    {
        return 'Keeps only the variants your delegating layouts name';
    }

    public function appliesTo(Project $project): bool
    {
        foreach ($this->families as $family) {
            if ($this->unused($project, $family) !== []) {
                return true;
            }
        }

        return false;
    }

    public function contribute(Plan $plan, Project $project, Report $report): void
    {
        foreach ($this->families as $family) {
            $keep = $this->inUse($project, $family);

            foreach ($this->unused($project, $family) as $name) {
                $plan->add(Stage::Move, new DeleteFile(
                    sprintf('%s/%s.blade.php', $this->directory($family), $name),
                    sprintf('delete %s/%s.blade.php (keeping "%s")', $this->directory($family), $name, $keep),
                ));
            }
        }
    }

    /**
     * The folder holding a family's variants.
     */
    private function directory(string $family): string
    {
        return sprintf('resources/views/layouts/%s', $family);
    }

    /**
     * The layout every view names, which renders one variant and nothing else.
     */
    private function delegate(string $family): string
    {
        return sprintf('resources/views/layouts/%s.blade.php', $family);
    }

    /**
     * The variant a family's delegating layout renders.
     */
    private function inUse(Project $project, string $family): ?string
    {
        if (! $project->exists($this->delegate($family))) {
            return null;
        }

        $pattern = sprintf('/<x-layouts::%s\.([a-z0-9-]+)/', preg_quote($family, '/'));

        if (preg_match($pattern, $project->get($this->delegate($family)), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    private function unused(Project $project, string $family): array
    {
        $keep = $this->inUse($project, $family);

        if ($keep === null) {
            return [];
        }

        $matches = glob($project->path($this->directory($family)).'/*.blade.php');

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
