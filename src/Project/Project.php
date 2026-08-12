<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Project;

use Symfony\Component\Finder\Finder;

/**
 * A detected Livewire starter kit installation.
 *
 * Everything refit knows about the application before it starts planning. Paths
 * are resolved against the project root so the whole package can be pointed at a
 * fixture directory during tests.
 */
final class Project
{
    /**
     * @param  list<Feature>  $features
     */
    public function __construct(
        public readonly string $root,
        public readonly ComponentStyle $componentStyle,
        public readonly array $features,
        public readonly bool $fluxPro,
        public readonly bool $chiselPending,
    ) {}

    public function has(Feature $feature): bool
    {
        return in_array($feature, $this->features, strict: true);
    }

    /**
     * Resolve a project-relative path to an absolute one.
     */
    public function path(string $relative = ''): string
    {
        return $relative === ''
            ? $this->root
            : $this->root.DIRECTORY_SEPARATOR.ltrim($relative, '/');
    }

    public function exists(string $relative): bool
    {
        return file_exists($this->path($relative));
    }

    public function get(string $relative): string
    {
        $contents = @file_get_contents($this->path($relative));

        return $contents === false ? '' : $contents;
    }

    /**
     * Every Blade file in the application, as project-relative paths.
     *
     * Scanned live rather than cached: the plan moves and deletes files while it
     * runs, and the reconciliation pass has to see the settled tree.
     *
     * @return list<string>
     */
    public function blades(): array
    {
        $directory = $this->path('resources/views');

        if (! is_dir($directory)) {
            return [];
        }

        $finder = Finder::create()
            ->files()
            ->in($directory)
            ->name('*.blade.php')
            ->sortByName();

        $paths = [];

        foreach ($finder as $file) {
            $paths[] = 'resources/views/'.str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                $file->getRelativePathname(),
            );
        }

        return $paths;
    }

    /**
     * A short human description of the kit, for the command's summary line.
     */
    public function describe(): string
    {
        $parts = [$this->componentStyle->label()];

        foreach ($this->features as $feature) {
            $parts[] = $feature->label();
        }

        return implode(', ', $parts);
    }
}
