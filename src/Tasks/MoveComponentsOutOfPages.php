<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Tasks;

use Onelegstudios\Refit\Contracts\Task;
use Onelegstudios\Refit\Plan\Actions\ApplyLedgerRenames;
use Onelegstudios\Refit\Plan\Actions\MoveFile;
use Onelegstudios\Refit\Plan\Actions\RemoveDirectoryIfEmpty;
use Onelegstudios\Refit\Plan\Actions\RenameComponentStrings;
use Onelegstudios\Refit\Plan\Plan;
use Onelegstudios\Refit\Plan\RenameLedger;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Plan\Stage;
use Onelegstudios\Refit\Project\ComponentStyle;
use Onelegstudios\Refit\Project\Project;
use Symfony\Component\Finder\Finder;

/**
 * Move everything in `pages/` that no route renders into `components/`.
 *
 * The kit already knows the distinction. `components/⚡team-switcher.blade.php` is
 * a single-file Livewire component sitting where components go, rendered as
 * `<livewire:team-switcher />`. Its neighbours in the settings and teams sections
 * were left in `pages/`, where they are pages by folder and components by every
 * other measure:
 *
 *     <livewire:pages::settings.delete-user-modal />
 *
 * Nothing routes to that. It is a modal the profile page opens, wearing a
 * namespace that says the opposite — and the cost is that `pages/` stops meaning
 * anything, so the one question worth asking about a Livewire component, whether
 * it is a screen or a piece of one, has to be answered by reading it.
 *
 * A route is the only thing that makes a Livewire component a page, so a route is
 * what this task reads. Every view in a section that has at least one routed page,
 * and that no route names, moves to `resources/views/components` under the folder
 * it already sits in. That drops `pages::` from every reference and lands each one
 * on the name the class-component kit uses for the same component.
 *
 * A section nothing routes into is not a page directory in the first place and is
 * left alone. `pages/auth` is that case, and {@see MoveAuthViewsOutOfPages} is its
 * separate argument.
 *
 * `settings/layout.blade.php` travels with them. It is an anonymous Blade
 * component rather than a Livewire one, so it moves as `<x-settings.layout>` —
 * the path the class-component kit ships it at, exactly.
 */
final class MoveComponentsOutOfPages implements Task
{
    private const string PAGES = 'resources/views/pages';

    private const string COMPONENTS = 'resources/views/components';

    private const string NAMESPACE = 'pages::';

    public function key(): string
    {
        return 'components-out-of-pages';
    }

    public function group(): TaskGroup
    {
        return TaskGroup::Structure;
    }

    public function label(): string
    {
        return 'Move the non-page components out of pages';
    }

    public function hint(): string
    {
        return 'Only what a route renders stays — <livewire:pages::settings.delete-user-modal /> becomes <livewire:settings.delete-user-modal />';
    }

    public function appliesTo(Project $project): bool
    {
        return $this->moves($project) !== [];
    }

    public function contribute(Plan $plan, Project $project, Report $report): void
    {
        $ledger = new RenameLedger;
        $names = [];
        $moved = [];

        foreach ($this->moves($project) as $move) {
            if ($project->exists($move['target'])) {
                $report->warn("Left {$move['path']} alone: {$move['target']} already exists.");

                continue;
            }

            $plan->add(Stage::Move, new MoveFile($move['path'], $move['target']));

            $moved[] = $move['path'];

            $prefix = $move['livewire'] ? 'livewire:' : 'x-';

            $ledger->record($prefix.self::NAMESPACE.$move['name'], $prefix.$move['name']);

            // Only Livewire components go in the string map. `Livewire::test()`
            // names one by exactly the name the tag carries, but the string form
            // of an anonymous component is a view name — `components.settings.layout`
            // rather than `settings.layout` — which is a different rename.
            if ($move['livewire']) {
                $names[self::NAMESPACE.$move['name']] = $move['name'];
            }
        }

        if ($moved === []) {
            return;
        }

        foreach ($this->emptied($project, $moved) as $directory) {
            $plan->add(Stage::Move, new RemoveDirectoryIfEmpty($directory));
        }

        $plan->add(Stage::Reconcile, new ApplyLedgerRenames(
            $ledger,
            'the moved components to their unnamespaced names',
        ));

        if ($names !== []) {
            $plan->add(Stage::Reconcile, new RenameComponentStrings($names));
        }
    }

    /**
     * The views leaving `pages/`, with where each one lands.
     *
     * Read at planning time rather than while the plan runs: the only other task
     * that touches `pages/` is {@see MoveAuthViewsOutOfPages}, and it works on the
     * one section this task has already decided is none of its business.
     *
     * @return list<array{path: string, target: string, name: string, livewire: bool}>
     */
    private function moves(Project $project): array
    {
        if ($project->componentStyle !== ComponentStyle::SingleFile) {
            return [];
        }

        $views = $this->views($project);
        $routes = $this->routes($project);

        $sections = [];

        foreach ($views as $view) {
            if ($this->named($routes, $view['name'])) {
                $sections[$view['section']] = true;
            }
        }

        $moves = [];

        foreach ($views as $view) {
            if (! isset($sections[$view['section']]) || $this->named($routes, $view['name'])) {
                continue;
            }

            $moves[] = [
                'path' => $view['path'],
                'target' => $view['target'],
                'name' => $view['name'],
                'livewire' => $view['livewire'],
            ];
        }

        return $moves;
    }

    /**
     * Every view sitting in a section of `pages/`.
     *
     * A section is a directory: the kit puts nothing at the top level, and a view
     * with no section has no folder to carry over to `components/`.
     *
     * @return list<array{path: string, target: string, section: string, name: string, livewire: bool}>
     */
    private function views(Project $project): array
    {
        $directory = $project->path(self::PAGES);

        if (! is_dir($directory)) {
            return [];
        }

        $finder = Finder::create()
            ->files()
            ->in($directory)
            ->depth('>= 1')
            ->name('*.blade.php')
            ->sortByName();

        $views = [];

        foreach ($finder as $file) {
            $folder = str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePath());
            $filename = $file->getBasename();
            $basename = $file->getBasename('.blade.php');
            $slug = $this->slug($basename);

            $views[] = [
                'path' => sprintf('%s/%s/%s', self::PAGES, $folder, $filename),
                'target' => sprintf('%s/%s/%s', self::COMPONENTS, $folder, $filename),
                'section' => explode('/', $folder)[0],
                'name' => str_replace('/', '.', $folder).'.'.$slug,
                'livewire' => $slug !== $basename,
            ];
        }

        return $views;
    }

    /**
     * The name a component answers to, out of the filename it is stored under.
     *
     * Single-file Livewire components carry an installer-applied prefix that is
     * part of the real filename and no part of the name — which is also what tells
     * them apart from the anonymous Blade components sitting beside them.
     */
    private function slug(string $basename): string
    {
        return preg_replace('/^[^a-z0-9]+/iu', '', $basename) ?? $basename;
    }

    /**
     * Every route file, read as one string.
     */
    private function routes(Project $project): string
    {
        $matches = glob($project->path('routes').'/*.php');

        if ($matches === false) {
            return '';
        }

        $sources = [];

        foreach ($matches as $path) {
            $contents = @file_get_contents($path);

            if ($contents !== false) {
                $sources[] = $contents;
            }
        }

        return implode(PHP_EOL, $sources);
    }

    /**
     * Whether the routes name this component, as a whole quoted string.
     */
    private function named(string $routes, string $name): bool
    {
        foreach (["'", '"'] as $quote) {
            if (str_contains($routes, $quote.self::NAMESPACE.$name.$quote)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The directories the moves leave with nothing in them, deepest first.
     *
     * A section keeps its routed pages, so only the folders that existed purely to
     * hold components — `settings/two-factor` — come out. Anything with a page
     * still in it is never offered for removal, which keeps the plan free of a
     * line that could only ever report back that it did nothing.
     *
     * @param  list<string>  $moved
     * @return list<string>
     */
    private function emptied(Project $project, array $moved): array
    {
        $remaining = [];

        foreach ($this->views($project) as $view) {
            if (! in_array($view['path'], $moved, true)) {
                $remaining[] = $view['path'];
            }
        }

        $directories = [];

        foreach ($moved as $path) {
            $directory = dirname($path);

            if (in_array($directory, $directories, true)) {
                continue;
            }

            foreach ($remaining as $keeper) {
                if (str_starts_with($keeper, $directory.'/')) {
                    continue 2;
                }
            }

            $directories[] = $directory;
        }

        usort($directories, static fn (string $a, string $b): int => substr_count($b, '/') <=> substr_count($a, '/'));

        return $directories;
    }
}
