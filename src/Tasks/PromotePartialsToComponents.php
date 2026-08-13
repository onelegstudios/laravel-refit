<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Tasks;

use Onelegstudios\Refit\Contracts\Task;
use Onelegstudios\Refit\Plan\Actions\MoveFile;
use Onelegstudios\Refit\Plan\Actions\RemoveDirectoryIfEmpty;
use Onelegstudios\Refit\Plan\Actions\ReplaceIncludeWithComponent;
use Onelegstudios\Refit\Plan\Plan;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Plan\Stage;
use Onelegstudios\Refit\Project\Project;

/**
 * Turn `resources/views/partials` into anonymous Blade components.
 *
 * The kit ships two: `partials.head` and `partials.settings-heading`, pulled in
 * with `@include` 42 times between them. Both are self-contained, so the move is
 * mechanical — an `@include` that passes data is reported and left alone by
 * {@see ReplaceIncludeWithComponent}. Once every partial has moved out, the
 * directory itself goes too, unless something unplanned is still sitting in it.
 */
final class PromotePartialsToComponents implements Task
{
    private const string DIRECTORY = 'resources/views/partials';

    public function key(): string
    {
        return 'partials-to-components';
    }

    public function group(): TaskGroup
    {
        return TaskGroup::Structure;
    }

    public function label(): string
    {
        return 'Use components instead of partials';
    }

    public function hint(): string
    {
        return 'Moves resources/views/partials into components and swaps @include for <x-…/>';
    }

    public function appliesTo(Project $project): bool
    {
        return $this->partials($project) !== [];
    }

    public function contribute(Plan $plan, Project $project, Report $report): void
    {
        foreach ($this->partials($project) as $name) {
            $plan->add(Stage::Move, new MoveFile(
                sprintf('%s/%s.blade.php', self::DIRECTORY, $name),
                sprintf('resources/views/components/%s.blade.php', $name),
            ));

            $plan->add(Stage::Reconcile, new ReplaceIncludeWithComponent(
                'partials.'.$name,
                'x-'.$name,
            ));
        }

        $plan->add(Stage::Move, new RemoveDirectoryIfEmpty(self::DIRECTORY));
    }

    /**
     * @return list<string>
     */
    private function partials(Project $project): array
    {
        $matches = glob($project->path(self::DIRECTORY).'/*.blade.php');

        if ($matches === false) {
            return [];
        }

        return array_map(
            static fn (string $path): string => basename($path, '.blade.php'),
            $matches,
        );
    }
}
