<?php

declare(strict_types=1);

use Onelegstudios\Refit\Contracts\Task;
use Onelegstudios\Refit\Plan\Applier;
use Onelegstudios\Refit\Plan\Plan;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Project\Project;
use Onelegstudios\Refit\Project\ProjectDetector;
use Onelegstudios\Refit\Refit;
use Onelegstudios\Refit\Tasks\KeepOneAuthLayout;
use Onelegstudios\Refit\Tasks\NamespaceComponents;
use Onelegstudios\Refit\Tasks\PromotePartialsToComponents;
use Onelegstudios\Refit\Tasks\RemoveFluxProSource;

/**
 * Run one task against a throwaway copy of a fixture.
 *
 * @return array{Project, Report}
 */
function runTask(Task $task, string $kit): array
{
    $root = copyFixture($kit);
    $project = (new ProjectDetector)->detect($root);
    $plan = new Plan;
    $report = new Report;

    $task->contribute($plan, $project, $report);

    (new Applier)->apply($plan, $project, $report);

    return [$project, $report];
}

it('registers the configured tasks', function (): void {
    $refit = app(Refit::class);

    expect(array_map(fn (Task $task): string => $task->key(), $refit->tasks()))
        ->toBe([
            'partials-to-components',
            'namespace-components',
            'single-auth-layout',
            'remove-flux-pro-source',
        ]);
});

it('only offers tasks that fit the detected kit', function (): void {
    $refit = app(Refit::class);
    $keys = array_map(fn (Task $task): string => $task->key(), $refit->tasksFor(detectFixture('livewire')));

    expect($keys)->toContain('partials-to-components')
        ->and($keys)->toContain('remove-flux-pro-source');
});

it('turns partials into components and rewrites the includes', function (): void {
    [$project] = runTask(new PromotePartialsToComponents, 'livewire');

    expect($project->exists('resources/views/components/head.blade.php'))->toBeTrue()
        ->and($project->exists('resources/views/partials/head.blade.php'))->toBeFalse()
        ->and($project->get('resources/views/layouts/app/sidebar.blade.php'))
        ->toContain('<x-head />')
        ->not->toContain("@include('partials.head')");
});

it('leaves no partial include behind', function (string $kit): void {
    [$project] = runTask(new PromotePartialsToComponents, $kit);

    foreach ($project->blades() as $path) {
        expect($project->get($path))->not->toContain("@include('partials.");
    }
})->with(starterKits());

it('groups components by domain and repoints every reference', function (): void {
    [$project] = runTask(new NamespaceComponents, 'livewire');

    expect($project->exists('resources/views/components/brand/logo.blade.php'))->toBeTrue()
        ->and($project->exists('resources/views/components/auth/header.blade.php'))->toBeTrue()
        ->and($project->get('resources/views/layouts/app/sidebar.blade.php'))
        ->toContain('<x-brand.logo')
        ->not->toContain('<x-app-logo');
});

it('drops the prefix the folder now carries', function (): void {
    [$project] = runTask(new NamespaceComponents, 'livewire');

    expect($project->get('resources/views/pages/auth/login.blade.php'))
        ->toContain('<x-auth.session-status')
        ->not->toContain('<x-auth-session-status');
});

it('leaves a component with nothing to group with in the fallback folder', function (): void {
    [$project] = runTask(new NamespaceComponents, 'livewire');

    expect($project->exists('resources/views/components/ui/placeholder-pattern.blade.php'))->toBeTrue()
        ->and($project->get('resources/views/dashboard.blade.php'))
        ->toContain('<x-ui.placeholder-pattern');
});

it('does not confuse two components sharing a name prefix', function (): void {
    [$project] = runTask(new NamespaceComponents, 'livewire');

    $logo = $project->get('resources/views/components/brand/logo.blade.php');

    expect($logo)->toContain('<x-brand.logo-icon')
        ->and($project->exists('resources/views/components/brand/logo-icon.blade.php'))->toBeTrue();
});

it('leaves no loose component behind in any kit', function (string $kit): void {
    [$project] = runTask(new NamespaceComponents, $kit);

    expect($project->looseComponents())->toBe([]);
})->with(starterKits());

it('keeps only the auth layout the application renders', function (): void {
    [$project] = runTask(new KeepOneAuthLayout, 'livewire');

    expect($project->exists('resources/views/layouts/auth/simple.blade.php'))->toBeTrue()
        ->and($project->exists('resources/views/layouts/auth/card.blade.php'))->toBeFalse()
        ->and($project->exists('resources/views/layouts/auth/split.blade.php'))->toBeFalse();
});

it('drops the Flux Pro source line', function (): void {
    [$project] = runTask(new RemoveFluxProSource, 'livewire');

    expect($project->get('resources/css/app.css'))
        ->not->toContain('flux-pro')
        ->toContain("@import 'tailwindcss';");
});

it('does not offer the Flux Pro cleanup when Flux Pro is installed', function (): void {
    $root = copyFixture('livewire');

    file_put_contents($root.'/composer.lock', '{"packages":[{"name":"livewire/flux-pro"}]}');

    $project = (new ProjectDetector)->detect($root);

    expect($project->fluxPro)->toBeTrue()
        ->and((new RemoveFluxProSource)->appliesTo($project))->toBeFalse();
});
