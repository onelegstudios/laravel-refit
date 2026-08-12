<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan\Actions;

use Onelegstudios\Refit\Contracts\Action;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Project\Project;
use RuntimeException;

final class MoveFile implements Action
{
    public function __construct(
        private readonly string $from,
        private readonly string $to,
    ) {}

    public function describe(): string
    {
        return sprintf('move   %s -> %s', $this->from, $this->to);
    }

    public function apply(Project $project, Report $report): void
    {
        $source = $project->path($this->from);
        $target = $project->path($this->to);

        if (! file_exists($source)) {
            $report->warn("Skipped moving [{$this->from}] — it does not exist.");

            return;
        }

        $directory = dirname($target);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create [{$directory}].");
        }

        if (! rename($source, $target)) {
            throw new RuntimeException("Unable to move [{$this->from}] to [{$this->to}].");
        }

        $report->changed($this->to);
        $report->note("Moved {$this->from} to {$this->to}");
    }
}
