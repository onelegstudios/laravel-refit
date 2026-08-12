<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Icons;

use Onelegstudios\Refit\Plan\Actions\DeleteFile;
use Onelegstudios\Refit\Plan\Actions\RewriteIconNames;
use Onelegstudios\Refit\Plan\Actions\WriteFile;
use Onelegstudios\Refit\Plan\Plan;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Plan\Stage;
use Onelegstudios\Refit\Project\Project;

/**
 * Turns an icon strategy into plan actions.
 *
 * The two directions are not symmetric. Going to Heroicons is subtraction: delete
 * the vendored Lucide overrides and point their usages at names Flux already
 * ships. Going to Lucide is generation, and has to cover the icons Flux renders
 * from inside its own components as well as the ones in application code.
 */
final class IconPlanner
{
    private const string OVERRIDE_DIRECTORY = 'resources/views/flux/icon';

    public function __construct(
        private readonly IconScanner $scanner = new IconScanner,
        private readonly OverrideGenerator $generator = new OverrideGenerator,
    ) {}

    public function contribute(Plan $plan, Project $project, IconStrategy $strategy, Report $report): void
    {
        match ($strategy) {
            IconStrategy::Keep => $report->note('Icons left as they are — Heroicons with the kit\'s vendored Lucide overrides.'),
            IconStrategy::Heroicons => $this->toHeroicons($plan, $project, $report),
            IconStrategy::Lucide => $this->toLucide($plan, $project, $report),
        };
    }

    /**
     * Existing override files, keyed by the icon name they define.
     *
     * @return array<string, string> Name mapped to its project-relative path.
     */
    public function existingOverrides(Project $project): array
    {
        $directory = $project->path(self::OVERRIDE_DIRECTORY);

        if (! is_dir($directory)) {
            return [];
        }

        $matches = glob($directory.'/*.blade.php');
        $overrides = [];

        foreach ($matches === false ? [] : $matches as $path) {
            $name = basename($path, '.blade.php');

            $overrides[$name] = self::OVERRIDE_DIRECTORY.'/'.basename($path);
        }

        ksort($overrides);

        return $overrides;
    }

    private function toHeroicons(Plan $plan, Project $project, Report $report): void
    {
        $renames = [];

        foreach ($this->existingOverrides($project) as $name => $path) {
            $heroicon = IconMap::toHeroicons($name);

            if ($heroicon === null) {
                $report->warn(sprintf(
                    'Kept %s — refit has no Heroicons equivalent for "%s".',
                    $path,
                    $name,
                ));

                continue;
            }

            $renames[$name] = $heroicon;

            $plan->add(Stage::Move, new DeleteFile(
                $path,
                sprintf('delete %s (now Heroicons "%s")', $path, $heroicon),
            ));
        }

        if ($renames === []) {
            $report->note('No Lucide overrides found — the kit is already all Heroicons.');

            return;
        }

        $plan->add(Stage::Reconcile, new RewriteIconNames($renames, 'Lucide to Heroicons'));
    }

    private function toLucide(Plan $plan, Project $project, Report $report): void
    {
        $renames = [];

        /** @var array<string, string> $overrides Override filename mapped to bundled Lucide art. */
        $overrides = [];

        foreach ($this->scanner->scan($project) as $name => $paths) {
            if (IconMap::isReserved($name)) {
                continue;
            }

            // Names the kit already vendors as Lucide need art, but no rewrite.
            if ($this->generator->has($name) && IconMap::toLucide($name) === null) {
                $overrides[$name] = $name;

                continue;
            }

            $lucide = IconMap::toLucide($name);

            if ($lucide === null) {
                $report->warn(sprintf(
                    'No Lucide translation for "%s" — still Heroicons in %s.',
                    $name,
                    implode(', ', $paths),
                ));

                continue;
            }

            // A translation refit has no artwork for cannot be written, and the
            // rename has to be dropped with it: pointing a usage at an override
            // that never gets written would leave a blank where the icon was.
            if (! $this->generator->has($lucide)) {
                $report->warn(sprintf(
                    'No Lucide artwork bundled for "%s" — "%s" stays Heroicons in %s.',
                    $lucide,
                    $name,
                    implode(', ', $paths),
                ));

                continue;
            }

            if ($lucide !== $name) {
                $renames[$name] = $lucide;
            }

            $overrides[$lucide] = $lucide;
        }

        foreach ($this->fluxInternals($project, $report) as $name) {
            if (IconMap::isReserved($name)) {
                continue;
            }

            $lucide = IconMap::toLucide($name);

            if ($lucide === null || ! $this->generator->has($lucide)) {
                continue;
            }

            // Keyed by the Heroicons name: Flux's own markup asks for that, and
            // refit cannot rewrite code inside the vendor directory.
            $overrides[$name] = $lucide;
        }

        ksort($overrides);

        foreach ($overrides as $filename => $art) {
            $plan->add(Stage::Write, new WriteFile(
                sprintf('%s/%s.blade.php', self::OVERRIDE_DIRECTORY, $filename),
                $this->generator->render($art, alias: $filename === $art ? null : $art),
                sprintf(
                    'write  %s/%s.blade.php%s',
                    self::OVERRIDE_DIRECTORY,
                    $filename,
                    $filename === $art ? '' : sprintf(' (Lucide "%s", for Flux internals)', $art),
                ),
            ));
        }

        if ($renames !== []) {
            $plan->add(Stage::Reconcile, new RewriteIconNames($renames, 'Heroicons to Lucide'));
        }
    }

    /**
     * @return list<string>
     */
    private function fluxInternals(Project $project, Report $report): array
    {
        $scanned = $this->scanner->scanFluxPackage($project);

        if ($scanned !== []) {
            $untranslatable = array_values(array_filter(
                $scanned,
                static fn (string $name): bool => ! IconMap::isReserved($name)
                    && IconMap::toLucide($name) === null,
            ));

            if ($untranslatable !== []) {
                $report->warn(sprintf(
                    'Flux renders these itself and refit has no Lucide translation, so they stay Heroicons: %s.',
                    implode(', ', $untranslatable),
                ));
            }

            return $scanned;
        }

        $report->note('Flux is not installed here, so refit used its built-in list of the icons Flux renders internally.');

        return IconMap::FLUX_INTERNAL_FALLBACK;
    }
}
