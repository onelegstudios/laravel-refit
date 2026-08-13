<?php

declare(strict_types=1);

use Onelegstudios\Refit\Icons\IconPlanner;
use Onelegstudios\Refit\Icons\IconScanner;
use Onelegstudios\Refit\Icons\IconStrategy;
use Onelegstudios\Refit\Icons\OverrideGenerator;
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

/**
 * A throwaway project holding a single view, for cases a starter kit fixture
 * would only make harder to read.
 */
function projectWithView(string $path, string $source): string
{
    $root = sys_get_temp_dir().'/refit-view-'.bin2hex(random_bytes(6));

    mkdir(dirname($root.'/'.$path), 0755, true);
    file_put_contents($root.'/'.$path, $source);

    register_shutdown_function(static fn () => deleteDirectory($root));

    return $root;
}

/**
 * The bundled Lucide artwork with one name removed, standing in for a mapping
 * that points at art refit does not ship.
 */
function iconsWithout(string $name): string
{
    $directory = sys_get_temp_dir().'/refit-icons-'.bin2hex(random_bytes(6));

    copyDirectory(dirname(__DIR__, 2).'/resources/icons/lucide', $directory);
    unlink($directory.'/'.$name.'.svg');

    register_shutdown_function(static fn () => deleteDirectory($directory));

    return $directory;
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

    // Every name still referenced must resolve to an override we wrote —
    // including `loading`, which keeps Flux's name but gets Lucide artwork.
    expect(array_values(array_diff($remaining, $overrides)))->toBe([]);
})->with(starterKits());

it('overrides the Flux spinner in place, still spinning', function (): void {
    $root = copyFixture('livewire');
    $project = (new ProjectDetector)->detect($root);

    [$plan, $report] = planIcons($root, IconStrategy::Lucide);

    expect($plan->describe())
        ->toContain('  write  resources/views/flux/icon/loading.blade.php (Lucide "loader-circle", in place of Flux\'s own)');

    (new Applier)->apply($plan, $project, $report);

    // Flux renders `flux:icon.loading` from inside its own components, so the
    // usages have to keep the name the override is written at.
    expect($project->get('resources/views/flux/icon/loading.blade.php'))
        ->toContain("Flux::classes('shrink-0 animate-spin')")
        ->and(array_keys((new IconScanner)->scan($project)))->toContain('loading')
        ->and($plan->describe())->not->toContain('loader-circle.blade.php');
});

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

it('reports an icon whose Lucide artwork is missing rather than failing', function (): void {
    $root = projectWithView('resources/views/mailbox.blade.php', '<flux:icon.envelope />');
    $project = (new ProjectDetector)->detect($root);

    $plan = new Plan;
    $report = new Report;

    (new IconPlanner(generator: new OverrideGenerator(iconDirectory: iconsWithout('mail'))))
        ->contribute($plan, $project, IconStrategy::Lucide, $report);

    (new Applier)->apply($plan, $project, $report);

    expect($report->warnings())->toContain('No Lucide artwork bundled for "mail" — "envelope" stays Heroicons in resources/views/mailbox.blade.php.')
        ->and($plan->describe())->not->toContain('flux/icon/mail.blade.php')
        // Renaming without an override to land on would have broken the icon.
        ->and($project->get('resources/views/mailbox.blade.php'))->toBe('<flux:icon.envelope />');
});

it('reports nothing it could not translate for a stock kit', function (string $kit): void {
    [, $report] = planIcons(requireFixture($kit), IconStrategy::Lucide);

    expect($report->warnings())->toBe([]);
})->with(starterKits());
