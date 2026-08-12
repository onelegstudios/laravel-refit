<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Icons;

use FilesystemIterator;
use Onelegstudios\Refit\Blade\TagParser;
use Onelegstudios\Refit\Project\Project;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Finds every icon name an application references.
 *
 * Two forms carry a name: the attribute form (`icon="home"`) and the tag form
 * (`<flux:icon.key />`). Both are collected with the files they appear in, so the
 * report can point at a specific view when a name has no translation.
 */
final class IconScanner
{
    public function __construct(private readonly TagParser $parser = new TagParser) {}

    /**
     * @return array<string, list<string>> Icon name mapped to the paths using it.
     */
    public function scan(Project $project): array
    {
        $found = [];

        foreach ($project->blades() as $path) {
            foreach ($this->scanSource($project->get($path)) as $name) {
                $found[$name][] = $path;

                $found[$name] = array_values(array_unique($found[$name]));
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * @return list<string>
     */
    public function scanSource(string $source): array
    {
        $names = [];

        foreach ($this->parser->parse($source, 'flux:') as $tag) {
            $suffix = $tag->nameAfter('flux:icon.');

            if ($suffix !== null && $suffix !== '') {
                $names[] = $suffix;
            }

            foreach ($tag->attributes as $attribute) {
                if (! IconMap::namesAnIcon($tag->name, $attribute->name)) {
                    continue;
                }

                // Bound values hold a PHP expression, not a name refit can read.
                if ($attribute->isBound() || $attribute->isBoolean()) {
                    continue;
                }

                $value = (string) $attribute->value;

                if ($value !== '') {
                    $names[] = $value;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Icon names a component defaults its own icon prop to.
     *
     * Flux's `error` component renders `exclamation-triangle` without ever
     * writing it on a tag — it is the default of the `icon` prop, so only the
     * `@props` array names it. `name` is deliberately not read here: `@props`
     * declares one on components that have nothing to do with icons.
     *
     * @return list<string>
     */
    public function scanPropDefaults(string $source): array
    {
        if (preg_match_all('/@props\s*\(\s*\[(.*?)]\s*\)/s', $source, $blocks) === 0) {
            return [];
        }

        $keys = implode('|', array_map(preg_quote(...), IconMap::NAME_ATTRIBUTES));
        $names = [];

        foreach ($blocks[1] as $block) {
            preg_match_all(
                sprintf('/([\'"])(?:%s)\1\s*=>\s*([\'"])([^\'"]+)\2/', $keys),
                $block,
                $defaults,
            );

            foreach ($defaults[3] as $name) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Icon names the installed Flux package renders from inside its own
     * components, discovered by reading its Blade stubs.
     *
     * Scanning beats guessing: it stays correct as Flux changes. When the package
     * is not installed — running against a fixture, for instance — the caller
     * falls back to {@see IconMap::FLUX_INTERNAL_FALLBACK}.
     *
     * @return list<string>
     */
    public function scanFluxPackage(Project $project): array
    {
        $names = [];

        foreach (['vendor/livewire/flux/stubs', 'vendor/livewire/flux-pro/stubs'] as $relative) {
            $directory = $project->path($relative);

            if (! is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $contents = @file_get_contents($file->getPathname());

                if ($contents === false) {
                    continue;
                }

                foreach ([...$this->scanSource($contents), ...$this->scanPropDefaults($contents)] as $name) {
                    $names[] = $name;
                }
            }
        }

        $names = array_values(array_unique($names));

        sort($names);

        return $names;
    }
}
