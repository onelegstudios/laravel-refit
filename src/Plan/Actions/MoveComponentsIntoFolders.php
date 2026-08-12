<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan\Actions;

use Onelegstudios\Refit\Contracts\Action;
use Onelegstudios\Refit\Plan\RenameLedger;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Project\ComponentGrouper;
use Onelegstudios\Refit\Project\Project;
use RuntimeException;

/**
 * Sort every loose anonymous component into a subfolder of its own domain.
 *
 * {@see ComponentGrouper} decides where each one goes; this only moves files and
 * records what it did. The directory is read when the action runs rather than
 * when it is planned, so a partial promoted into `components/` by an earlier
 * task in the same run is grouped too. Each move is recorded in the ledger for
 * the reconcile pass.
 */
final class MoveComponentsIntoFolders implements Action
{
    private const string DIRECTORY = 'resources/views/components';

    /**
     * @param  array<string, string>  $groups  component name => `folder/name`
     */
    public function __construct(
        private readonly array $groups,
        private readonly string $fallback,
        private readonly RenameLedger $ledger,
    ) {}

    public function describe(): string
    {
        return sprintf('group  %s/*.blade.php into subfolders', self::DIRECTORY);
    }

    public function apply(Project $project, Report $report): void
    {
        $targets = (new ComponentGrouper)->group(
            $project->looseComponents(),
            $this->groups,
            $this->fallback,
        );

        foreach ($targets as $name => $target) {
            $from = sprintf('%s/%s.blade.php', self::DIRECTORY, $name);
            $to = sprintf('%s/%s.blade.php', self::DIRECTORY, $target);

            if ($project->exists($to)) {
                $report->warn("Left {$from} alone: {$to} already exists.");

                continue;
            }

            $this->directory(dirname($project->path($to)));

            if (! rename($project->path($from), $project->path($to))) {
                throw new RuntimeException("Unable to move [{$from}] to [{$to}].");
            }

            $this->ledger->record('x-'.$name, 'x-'.str_replace('/', '.', $target));

            $report->note("Moved {$from} to {$to}");
        }
    }

    private function directory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException("Unable to create [{$path}].");
        }
    }
}
