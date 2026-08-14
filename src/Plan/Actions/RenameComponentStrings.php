<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan\Actions;

use Onelegstudios\Refit\Contracts\Action;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Project\Project;
use Symfony\Component\Finder\Finder;

/**
 * Repoint the Livewire component names that PHP files carry as strings.
 *
 * The Blade sweeps handle tags; this is the other half. A Livewire component is
 * named by a string in two places the kit actually uses — `Route::livewire()` and
 * `Livewire::test()` — and the second one matters: moving a component the kit's
 * own suite names would leave the tests red on a tree refit had just tidied.
 *
 * Only whole quoted literals are replaced, so a name that happens to be the
 * leading edge of a longer one cannot be half-rewritten, and prose that merely
 * mentions a component is left alone.
 */
final class RenameComponentStrings implements Action
{
    /**
     * @var list<string>
     */
    private const array DIRECTORIES = ['app', 'routes', 'tests'];

    /**
     * @param  array<string, string>  $names  old component name => new one
     */
    public function __construct(private readonly array $names) {}

    public function describe(): string
    {
        return sprintf(
            'rename %d component name(s) in %s',
            count($this->names),
            implode('/, ', self::DIRECTORIES).'/',
        );
    }

    public function apply(Project $project, Report $report): void
    {
        if ($this->names === []) {
            return;
        }

        $files = 0;

        foreach ($this->files($project) as $path) {
            $source = $project->get($path);
            $rewritten = $this->rename($source);

            if ($rewritten === $source) {
                continue;
            }

            file_put_contents($project->path($path), $rewritten);

            $report->changed($path);
            $files++;
        }

        if ($files > 0) {
            $report->note(sprintf(
                'Repointed %d component name(s) in %d PHP file(s)',
                count($this->names),
                $files,
            ));
        }
    }

    private function rename(string $source): string
    {
        foreach ($this->names as $from => $to) {
            foreach (["'", '"'] as $quote) {
                $source = str_replace($quote.$from.$quote, $quote.$to.$quote, $source);
            }
        }

        return $source;
    }

    /**
     * Every PHP file in the directories an application names components from.
     *
     * @return list<string>
     */
    private function files(Project $project): array
    {
        $directories = [];

        foreach (self::DIRECTORIES as $directory) {
            if (is_dir($project->path($directory))) {
                $directories[] = $project->path($directory);
            }
        }

        if ($directories === []) {
            return [];
        }

        $finder = Finder::create()
            ->files()
            ->in($directories)
            ->name('*.php')
            ->sortByName();

        $root = strlen($project->path()) + 1;
        $paths = [];

        foreach ($finder as $file) {
            $paths[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), $root));
        }

        return $paths;
    }
}
