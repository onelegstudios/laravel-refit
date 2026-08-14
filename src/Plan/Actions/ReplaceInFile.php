<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan\Actions;

use Onelegstudios\Refit\Contracts\Action;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Project\Project;

/**
 * Swap one literal string for another in a single file.
 *
 * The Blade sweeps handle the view tree; this is for the one PHP file that names
 * something the plan has just moved. Literal rather than a pattern, and scoped to
 * a named file, so what the preview line promises is all it can do.
 */
final class ReplaceInFile implements Action
{
    public function __construct(
        private readonly string $path,
        private readonly string $search,
        private readonly string $replace,
        private readonly ?string $description = null,
    ) {}

    public function describe(): string
    {
        return $this->description ?? sprintf(
            'edit   %s — "%s" -> "%s"',
            $this->path,
            $this->search,
            $this->replace,
        );
    }

    public function apply(Project $project, Report $report): void
    {
        if (! $project->exists($this->path)) {
            $report->warn("Skipped editing [{$this->path}] — it does not exist.");

            return;
        }

        $source = $project->get($this->path);
        $rewritten = str_replace($this->search, $this->replace, $source, $count);

        if ($count === 0) {
            $report->warn("Nothing in [{$this->path}] matched \"{$this->search}\".");

            return;
        }

        file_put_contents($project->path($this->path), $rewritten);

        $report->changed($this->path);
        $report->note(sprintf(
            'Rewrote %d occurrence(s) of "%s" in %s',
            $count,
            $this->search,
            $this->path,
        ));
    }
}
