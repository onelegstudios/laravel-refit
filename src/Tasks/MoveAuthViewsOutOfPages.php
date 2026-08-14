<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Tasks;

use Onelegstudios\Refit\Contracts\Task;
use Onelegstudios\Refit\Plan\Actions\MoveFile;
use Onelegstudios\Refit\Plan\Actions\RemoveDirectoryIfEmpty;
use Onelegstudios\Refit\Plan\Actions\ReplaceInFile;
use Onelegstudios\Refit\Plan\Plan;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Plan\Stage;
use Onelegstudios\Refit\Project\Project;

/**
 * Move the auth views out of `resources/views/pages`.
 *
 * `pages/` is Livewire's directory. Everything else in it is a single-file
 * component — an `⚡` on the filename, a class in the same file, a route pointing
 * at `pages::settings.profile`. The auth views are none of that. They are plain
 * Blade that Fortify renders itself:
 *
 *     Fortify::loginView(fn () => view('pages::auth.login'));
 *
 * The folder is the only thing they share with their neighbours, and it costs
 * something: it reads as though logging in were a Livewire page, and every
 * reference carries a namespace that means nothing here.
 *
 * They move to `resources/views/auth`, where a Laravel developer looks for
 * ordinary views, and `pages::auth.login` becomes `auth.login`. Fortify names
 * them in one place, so `FortifyServiceProvider` is the whole reconciliation —
 * nothing in the Blade tree points at them.
 *
 * The class-component kit keeps the same views under `resources/views/livewire`,
 * where the argument is the same one. That is a different pair of names, so pass
 * them to the constructor rather than being surprised by a second default.
 */
final class MoveAuthViewsOutOfPages implements Task
{
    private const string SOURCE = 'resources/views/pages/auth';

    private const string SOURCE_PREFIX = 'pages::auth.';

    private const string TARGET = 'resources/views/auth';

    private const string TARGET_PREFIX = 'auth.';

    private const string PROVIDER = 'app/Providers/FortifyServiceProvider.php';

    /**
     * @param  string  $source  the directory the auth views sit in
     * @param  string  $prefix  the view name they answer to from there
     */
    public function __construct(
        private readonly string $source = self::SOURCE,
        private readonly string $prefix = self::SOURCE_PREFIX,
    ) {}

    public function key(): string
    {
        return 'auth-views-out-of-pages';
    }

    public function group(): TaskGroup
    {
        return TaskGroup::Structure;
    }

    public function label(): string
    {
        return 'Move the auth views out of pages';
    }

    public function hint(): string
    {
        return 'They are plain Blade, not Livewire pages — view(\'auth.login\') from resources/views/auth';
    }

    public function appliesTo(Project $project): bool
    {
        return $this->views($project) !== [];
    }

    public function contribute(Plan $plan, Project $project, Report $report): void
    {
        foreach ($this->views($project) as $name) {
            $plan->add(Stage::Move, new MoveFile(
                sprintf('%s/%s.blade.php', $this->source, $name),
                sprintf('%s/%s.blade.php', self::TARGET, $name),
            ));
        }

        $plan->add(Stage::Move, new RemoveDirectoryIfEmpty($this->source));

        $plan->add(Stage::Reconcile, new ReplaceInFile(
            self::PROVIDER,
            $this->prefix,
            self::TARGET_PREFIX,
            sprintf(
                'edit   %s — view(\'%slogin\') -> view(\'%slogin\')',
                self::PROVIDER,
                $this->prefix,
                self::TARGET_PREFIX,
            ),
        ));
    }

    /**
     * The auth views waiting in the source directory, as bare names.
     *
     * Single-file components carry an installer-applied prefix on the filename,
     * so anything wearing one is a Livewire page after all and stays put.
     *
     * @return list<string>
     */
    private function views(Project $project): array
    {
        $matches = glob($project->path($this->source).'/*.blade.php');

        if ($matches === false) {
            return [];
        }

        $names = [];

        foreach ($matches as $path) {
            $name = basename($path, '.blade.php');

            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name) !== 1) {
                continue;
            }

            $names[] = $name;
        }

        return $names;
    }
}
