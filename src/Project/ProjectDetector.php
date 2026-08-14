<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Project;

/**
 * Builds a {@see Project} by probing the filesystem.
 *
 * Refit runs after `install:features` has chiselled the kit, so the file tree
 * varies by the answers given at `laravel new`. Every signal here is a file that
 * either survived or did not — nothing is asked that disk can answer.
 */
final class ProjectDetector
{
    public function detect(string $root): Project
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);

        return new Project(
            root: $root,
            componentStyle: $this->componentStyle($root),
            features: $this->features($root),
            fluxPro: $this->hasFluxPro($root),
            chiselPending: file_exists($root.'/chisel.php'),
        );
    }

    /**
     * The class-component variant is the only one that ships app/Livewire.
     */
    private function componentStyle(string $root): ComponentStyle
    {
        return is_dir($root.'/app/Livewire/Settings')
            ? ComponentStyle::ClassBased
            : ComponentStyle::SingleFile;
    }

    /**
     * @return list<Feature>
     */
    private function features(string $root): array
    {
        $features = [];

        if (file_exists($root.'/app/Models/Team.php')) {
            $features[] = Feature::Teams;
        }

        if ($this->requires($root, 'laravel/workos')) {
            $features[] = Feature::WorkOs;
        }

        if (file_exists($root.'/resources/js/passkeys.js')) {
            $features[] = Feature::Passkeys;
        }

        if ($this->hasView($root, 'auth/two-factor-challenge')) {
            $features[] = Feature::TwoFactor;
        }

        if ($this->hasView($root, 'auth/register')) {
            $features[] = Feature::Registration;
        }

        return $features;
    }

    /**
     * Look for a kit view under either component style's directory.
     *
     * Single-file components carry an emoji prefix on the filename, so the check
     * globs rather than testing an exact path.
     *
     * `resources/views` itself is searched last because a previous run may have
     * moved the auth views up out of the kit's directory. A feature is detected
     * from a view that exists, not from where the installer happened to put it.
     */
    private function hasView(string $root, string $name): bool
    {
        $directory = dirname($name);
        $file = basename($name);

        foreach (['resources/views/pages', 'resources/views/livewire', 'resources/views'] as $base) {
            $matches = glob(sprintf('%s/%s/%s/*%s.blade.php', $root, $base, $directory, $file));

            if ($matches !== false && $matches !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flux Pro is never in composer.json — it is a private repository the user
     * adds themselves — so the lock file is the honest signal.
     */
    private function hasFluxPro(string $root): bool
    {
        if ($this->requires($root, 'livewire/flux-pro')) {
            return true;
        }

        $lock = @file_get_contents($root.'/composer.lock');

        return $lock !== false && str_contains($lock, '"livewire/flux-pro"');
    }

    private function requires(string $root, string $package): bool
    {
        $contents = @file_get_contents($root.'/composer.json');

        if ($contents === false) {
            return false;
        }

        $manifest = json_decode($contents, true);

        if (! is_array($manifest)) {
            return false;
        }

        foreach (['require', 'require-dev'] as $section) {
            $packages = $manifest[$section] ?? null;

            if (is_array($packages) && array_key_exists($package, $packages)) {
                return true;
            }
        }

        return false;
    }
}
