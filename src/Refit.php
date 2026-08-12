<?php

declare(strict_types=1);

namespace Onelegstudios\Refit;

use Onelegstudios\Refit\Contracts\Task;
use Onelegstudios\Refit\Project\Project;
use Onelegstudios\Refit\Tasks\TaskGroup;

/**
 * The task registry.
 *
 * Packages register their own jobs from a service provider:
 *
 *     Refit::task(new MyCustomTask);
 */
class Refit
{
    /**
     * @var list<Task>
     */
    private array $tasks = [];

    public function task(Task ...$tasks): static
    {
        foreach ($tasks as $task) {
            $this->tasks[] = $task;
        }

        return $this;
    }

    /**
     * @return list<Task>
     */
    public function tasks(): array
    {
        return $this->tasks;
    }

    /**
     * The tasks that make sense for a given project, grouped and ordered.
     *
     * @return list<Task>
     */
    public function tasksFor(Project $project): array
    {
        $applicable = array_values(array_filter(
            $this->tasks,
            static fn (Task $task): bool => $task->appliesTo($project),
        ));

        usort($applicable, static function (Task $a, Task $b): int {
            return [$a->group()->order(), $a->label()] <=> [$b->group()->order(), $b->label()];
        });

        return $applicable;
    }

    /**
     * @param  list<string>  $keys
     * @return list<Task>
     */
    public function resolve(array $keys): array
    {
        return array_values(array_filter(
            $this->tasks,
            static fn (Task $task): bool => in_array($task->key(), $keys, true),
        ));
    }

    /**
     * Applicable tasks as prompt options, keyed by task key.
     *
     * @return array<string, string>
     */
    public function options(Project $project): array
    {
        $options = [];

        foreach ($this->tasksFor($project) as $task) {
            $options[$task->key()] = sprintf(
                '[%s] %s',
                $task->group()->label(),
                $task->label(),
            );
        }

        return $options;
    }

    /**
     * @return list<TaskGroup>
     */
    public function groups(): array
    {
        return TaskGroup::cases();
    }
}
