<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan\Actions;

use Onelegstudios\Refit\Contracts\Action;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Project\Project;

final class RemoveLinesContaining implements Action
{
    public function __construct(
        private readonly string $path,
        private readonly string $needle,
        private readonly ?string $description = null,
    ) {}

    public function describe(): string
    {
        return $this->description ?? sprintf('edit   %s — drop lines containing "%s"', $this->path, $this->needle);
    }

    public function apply(Project $project, Report $report): void
    {
        if (! $project->exists($this->path)) {
            $report->warn("Skipped editing [{$this->path}] — it does not exist.");

            return;
        }

        $source = $project->get($this->path);
        $lines = preg_split('/\R/', $source);

        if ($lines === false) {
            return;
        }

        $kept = array_values(array_filter(
            $lines,
            fn (string $line): bool => ! str_contains($line, $this->needle),
        ));

        if (count($kept) === count($lines)) {
            $report->warn("Nothing in [{$this->path}] matched \"{$this->needle}\".");

            return;
        }

        file_put_contents($project->path($this->path), implode(PHP_EOL, $kept));

        $report->changed($this->path);
        $report->note(sprintf(
            'Removed %d line(s) from %s',
            count($lines) - count($kept),
            $this->path,
        ));
    }
}
