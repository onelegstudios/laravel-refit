<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Tasks;

use Onelegstudios\Refit\Contracts\Task;
use Onelegstudios\Refit\Plan\Actions\ApplyLedgerRenames;
use Onelegstudios\Refit\Plan\Actions\MoveComponentsIntoFolders;
use Onelegstudios\Refit\Plan\Plan;
use Onelegstudios\Refit\Plan\RenameLedger;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Plan\Stage;
use Onelegstudios\Refit\Project\Project;

/**
 * Sort the loose anonymous components into folders by what they are for.
 *
 * `<x-app-logo />` becomes `<x-brand.logo />`: the folder carries the prefix, so
 * the component name drops it. Only top-level files move — anything already in a
 * subfolder is namespaced, and Livewire's own `x-layouts::` and `x-pages::` views
 * are a different mechanism entirely.
 *
 * The map below is the shipped opinion for the Livewire starter kit; everything
 * else is grouped by shared prefix or lands in the fallback folder. Pass your own
 * map to the constructor to lay the tree out differently.
 *
 * The directory is read while the plan runs, not while it is built, so a partial
 * promoted into `components/` by another task in the same run is grouped too.
 * Without that, picking both structure tasks left a components directory half
 * namespaced.
 */
final class NamespaceComponents implements Task
{
    /**
     * @var array<string, string>
     */
    private const array GROUPS = [
        'app-logo' => 'brand/logo',
        'app-logo-icon' => 'brand/logo-icon',
        'auth-header' => 'auth/header',
        'auth-session-status' => 'auth/session-status',
        'passkey-registration' => 'auth/passkey-registration',
        'passkey-verify' => 'auth/passkey-verify',
        'desktop-user-menu' => 'layout/desktop-user-menu',
        'head' => 'layout/head',
        'settings-heading' => 'settings/heading',
        'team-invitation-alert' => 'teams/invitation-alert',
    ];

    /**
     * @param  array<string, string>  $groups  component name => `folder/name`
     */
    public function __construct(
        private readonly array $groups = self::GROUPS,
        private readonly string $fallback = 'ui',
    ) {}

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
        return 'Group components into folders';
    }

    public function hint(): string
    {
        return '<x-app-logo /> becomes <x-brand.logo />';
    }

    public function appliesTo(Project $project): bool
    {
        return $project->looseComponents() !== [];
    }

    public function contribute(Plan $plan, Project $project, Report $report): void
    {
        $ledger = new RenameLedger;

        $plan->add(Stage::Move, new MoveComponentsIntoFolders(
            $this->groups,
            $this->fallback,
            $ledger,
        ));

        $plan->add(Stage::Reconcile, new ApplyLedgerRenames(
            $ledger,
            'component references to their new folders',
        ));
    }
}
