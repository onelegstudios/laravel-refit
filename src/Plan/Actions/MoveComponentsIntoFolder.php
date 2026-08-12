<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan\Actions;

use Onelegstudios\Refit\Contracts\Action;
use Onelegstudios\Refit\Plan\RenameLedger;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Project\Project;
use RuntimeException;

/**
 * Move every loose anonymous component into a subfolder.
 *
 * The directory is read when the action runs rather than when it is planned, so
 * a partial promoted into `components/` by an earlier task in the same run is
 * picked up too. Each move is recorded in the ledger for the reconcile pass.
 */
final class MoveComponentsIntoFolder implements Action
{
    private const string DIRECTORY = 'resources/views/components';

    public function __construct(
        private readonly string $folder,
        private readonly RenameLedger $ledger,
    ) {}

    public function describe(): string
    {
        return sprintf('move   %s/*.blade.php -> %s/%s/', self::DIRECTORY, self::DIRECTORY, $this->folder);
    }

    public function apply(Project $project, Report $report): void
    {
        $target = $project->path(self::DIRECTORY.'/'.$this->folder);

        if (! is_dir($target) && ! mkdir($target, 0755, true) && ! is_dir($target)) {
            throw new RuntimeException("Unable to create [{$target}].");
        }

        foreach ($this->loose($project) as $name) {
            $from = sprintf('%s/%s.blade.php', self::DIRECTORY, $name);
            $to = sprintf('%s/%s/%s.blade.php', self::DIRECTORY, $this->folder, $name);

            if (! rename($project->path($from), $project->path($to))) {
                throw new RuntimeException("Unable to move [{$from}] to [{$to}].");
            }

            $this->ledger->record('x-'.$name, sprintf('x-%s.%s', $this->folder, $name));

            $report->note("Moved {$from} to {$to}");
        }
    }

    /**
     * Top-level component files, as bare names.
     *
     * @return list<string>
     */
    private function loose(Project $project): array
    {
        $matches = glob($project->path(self::DIRECTORY).'/*.blade.php');

        if ($matches === false) {
            return [];
        }

        $names = [];

        foreach ($matches as $path) {
            $name = basename($path, '.blade.php');

            // Single-file Livewire components carry an installer-applied prefix
            // that is part of the real filename. Leave those to Livewire.
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name) !== 1) {
                continue;
            }

            $names[] = $name;
        }

        return $names;
    }
}
