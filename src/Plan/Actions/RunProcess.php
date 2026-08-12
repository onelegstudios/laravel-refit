<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan\Actions;

use Onelegstudios\Refit\Contracts\Action;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Project\Project;
use Symfony\Component\Process\Process;

/**
 * Run a command in the project root.
 *
 * A failure is reported rather than thrown: these are finishing touches such as
 * Pint and asset builds, and a missing binary should not strand a project that
 * has already been rewritten successfully.
 */
final class RunProcess implements Action
{
    /**
     * @param  list<string>  $command
     */
    public function __construct(
        private readonly array $command,
        private readonly string $description,
        private readonly int $timeout = 300,
    ) {}

    public function describe(): string
    {
        return sprintf('run    %s (%s)', implode(' ', $this->command), $this->description);
    }

    public function apply(Project $project, Report $report): void
    {
        $process = new Process($this->command, $project->root, timeout: $this->timeout);

        $process->run();

        if ($process->isSuccessful()) {
            $report->note($this->description.' succeeded.');

            return;
        }

        $report->warn(sprintf(
            '%s failed (%s). Run `%s` yourself to see why.',
            $this->description,
            trim($process->getErrorOutput()) !== '' ? trim($process->getErrorOutput()) : 'no error output',
            implode(' ', $this->command),
        ));
    }
}
