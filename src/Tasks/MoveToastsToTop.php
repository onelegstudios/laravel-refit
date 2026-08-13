<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Tasks;

use Onelegstudios\Refit\Blade\TagRewriter;
use Onelegstudios\Refit\Contracts\Task;
use Onelegstudios\Refit\Plan\Actions\AddAttribute;
use Onelegstudios\Refit\Plan\Plan;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Plan\Stage;
use Onelegstudios\Refit\Project\Project;

/**
 * Stack toasts at the top of the screen instead of the bottom.
 *
 * Every kit renders a bare `<flux:toast.group>` in each layout, which leaves Flux
 * on its `bottom end` default — the corner a sidebar footer, a cookie bar or a
 * mobile keyboard tends to occupy. Adding `position="top end"` moves the stack out
 * of the way, and is the whole change: the group positions the toasts inside it.
 *
 * A group that already carries a position is left alone, so a project that has
 * made its own choice keeps it.
 */
final class MoveToastsToTop implements Task
{
    private const string TAG = 'flux:toast.group';

    private const string ATTRIBUTE = 'position';

    private const string POSITION = 'top end';

    public function __construct(private readonly TagRewriter $rewriter = new TagRewriter) {}

    public function key(): string
    {
        return 'toasts-at-top';
    }

    public function group(): TaskGroup
    {
        return TaskGroup::Structure;
    }

    public function label(): string
    {
        return 'Show toasts at the top of the screen';
    }

    public function hint(): string
    {
        return 'Adds position="top end" to the <flux:toast.group> in every layout';
    }

    public function appliesTo(Project $project): bool
    {
        foreach ($project->blades() as $path) {
            $source = $project->get($path);

            if (! str_contains($source, '<'.self::TAG)) {
                continue;
            }

            if ($this->reposition($source) !== $source) {
                return true;
            }
        }

        return false;
    }

    public function contribute(Plan $plan, Project $project, Report $report): void
    {
        $plan->add(Stage::Reconcile, new AddAttribute(
            self::TAG,
            self::ATTRIBUTE,
            self::POSITION,
            $this->rewriter,
        ));
    }

    /**
     * The rewrite this task would make, used to decide whether it has one to make.
     */
    private function reposition(string $source): string
    {
        return $this->rewriter->addAttribute($source, self::TAG, self::ATTRIBUTE, self::POSITION);
    }
}
