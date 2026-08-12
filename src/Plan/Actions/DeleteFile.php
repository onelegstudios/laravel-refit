<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan\Actions;

use Onelegstudios\Refit\Contracts\Action;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Project\Project;

final class DeleteFile implements Action
{
    public function __construct(
        private readonly string $path,
        private readonly ?string $description = null,
    ) {}

    public function describe(): string
    {
        return $this->description ?? 'delete '.$this->path;
    }

    public function apply(Project $project, Report $report): void
    {
        $target = $project->path($this->path);

        if (! file_exists($target)) {
            $report->warn("Skipped deleting [{$this->path}] — it was already gone.");

            return;
        }

        unlink($target);

        $report->note("Deleted {$this->path}");
    }
}
