<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Tasks;

use Onelegstudios\Refit\Contracts\Task;
use Onelegstudios\Refit\Plan\Actions\ApplyLedgerRenames;
use Onelegstudios\Refit\Plan\Actions\MoveComponentsIntoFolder;
use Onelegstudios\Refit\Plan\Plan;
use Onelegstudios\Refit\Plan\RenameLedger;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Plan\Stage;
use Onelegstudios\Refit\Project\Project;

/**
 * Gather the loose anonymous components into a subfolder.
 *
 * `<x-app-logo />` becomes `<x-ui.app-logo />`. Only top-level files move:
 * anything already in a subfolder is namespaced, and Livewire's own
 * `x-layouts::` and `x-pages::` views are a different mechanism entirely.
 *
 * The directory is read while the plan runs, not while it is built, so a partial
 * promoted into `components/` by another task in the same run lands in the
 * subfolder too. Without that, picking both structure jobs left a components
 * directory half namespaced.
 */
final class NamespaceComponents implements Task
{
    private const string DIRECTORY = 'resources/views/components';

    public function __construct(private readonly string $folder = 'ui') {}

    public function key(): string
    {
        return 'namespace-components';
    }

    public function group(): TaskGroup
    {
        return TaskGroup::Structure;
    }

    public function label(): string
    {
        return sprintf('Group components under components/%s', $this->folder);
    }

    public function hint(): string
    {
        return sprintf('<x-app-logo /> becomes <x-%s.app-logo />', $this->folder);
    }

    public function appliesTo(Project $project): bool
    {
        $matches = glob($project->path(self::DIRECTORY).'/*.blade.php');

        return $matches !== false && $matches !== [];
    }

    public function contribute(Plan $plan, Project $project, Report $report): void
    {
        $ledger = new RenameLedger;

        $plan->add(Stage::Move, new MoveComponentsIntoFolder($this->folder, $ledger));

        $plan->add(Stage::Reconcile, new ApplyLedgerRenames(
            $ledger,
            sprintf('component references to components/%s', $this->folder),
        ));
    }
}
