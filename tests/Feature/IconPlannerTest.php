<?php

declare(strict_types=1);

use Onelegstudios\Refit\Icons\IconPlanner;
use Onelegstudios\Refit\Icons\IconScanner;
use Onelegstudios\Refit\Icons\IconStrategy;
use Onelegstudios\Refit\Plan\Applier;
use Onelegstudios\Refit\Plan\Plan;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Project\ProjectDetector;

/**
 * @return array{Plan, Report}
 */
function planIcons(string $root, IconStrategy $strategy): array
{
    $plan = new Plan;
    $report = new Report;

    (new IconPlanner)->contribute($plan, (new ProjectDetector)->detect($root), $strategy, $report);

    return [$plan, $report];
}

it('plans nothing when the mix is being kept', function (string $kit): void {
    [$plan, $report] = planIcons(requireFixture($kit), IconStrategy::Keep);

    expect($plan->isEmpty())->toBeTrue()
        ->and($report->hasWarnings())->toBeFalse();
})->with(starterKits());

it('deletes the vendored Lucide overrides when going all Heroicons', function (): void {
    [$plan, $report] = planIcons(requireFixture('livewire'), IconStrategy::Heroicons);

    expect($plan->describe())->toContain('  delete resources/views/flux/icon/layout-grid.blade.php (now Heroicons "squares-2x2")')
        ->and($plan->describe())->toContain('  icons  Lucide to Heroicons (4 names)')
        ->and($report->hasWarnings())->toBeFalse();
});

it('writes an override for every icon when going all Lucide', function (string $kit): void {
    $root = copyFixture($kit);
    $project = (new ProjectDetector)->detect($root);

    [$plan, $report] = planIcons($root, IconStrategy::Lucide);

    (new Applier)->apply($plan, $project, $report);

    $overrides = array_map(
        static fn (string $path): string => basename($path, '.blade.php'),
        glob($root.'/resources/views/flux/icon/*.blade.php') ?: [],
    );

    $remaining = array_keys((new IconScanner)->scan($project));

    // Every name still referenced must resolve to an override we wrote, apart
    // from Flux's own spinner which is deliberately left alone.
    expect(array_values(array_diff($remaining, $overrides, ['loading'])))->toBe([]);
})->with(starterKits());

it('aliases the Heroicons name so Flux internals follow the switch', function (): void {
    [$plan] = planIcons(requireFixture('livewire'), IconStrategy::Lucide);

    // Flux asks for `x-mark` from inside its own components, and refit cannot
    // rewrite vendor code, so the override has to live at that name.
    expect($plan->describe())
        ->toContain('  write  resources/views/flux/icon/x-mark.blade.php (Lucide "x", for Flux internals)');
});

it('never rewrites a name attribute that is not an icon', function (): void {
    $root = copyFixture('livewire');
    $project = (new ProjectDetector)->detect($root);

    [$plan, $report] = planIcons($root, IconStrategy::Lucide);

    (new Applier)->apply($plan, $project, $report);

    expect($project->get('resources/views/pages/auth/login.blade.php'))
        ->toContain('name="email"')
        ->toContain('name="password"');
});

it('translates icons in all three written forms', function (): void {
    $root = copyFixture('livewire-teams');
    $project = (new ProjectDetector)->detect($root);

    [$plan, $report] = planIcons($root, IconStrategy::Lucide);

    (new Applier)->apply($plan, $project, $report);

    expect($project->get('resources/views/layouts/app/sidebar.blade.php'))->toContain('icon="house"')
        ->and($project->get('resources/views/components/desktop-user-menu.blade.php'))->toContain('name="chevrons-up-down"');
});

it('reports nothing it could not translate for a stock kit', function (string $kit): void {
    [, $report] = planIcons(requireFixture($kit), IconStrategy::Lucide);

    expect($report->warnings())->toBe([]);
})->with(starterKits());
