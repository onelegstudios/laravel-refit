<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan;

/**
 * Execution order for planned actions.
 *
 * Stages exist so contributors do not have to know about each other. Tasks move
 * and rename files freely; the single reconciliation pass afterwards is what sees
 * the settled tree and fixes every reference in one traversal.
 */
enum Stage: int
{
    /** Composer and npm changes, before anything reads the vendor directory. */
    case Dependencies = 10;

    /** Creating or overwriting files. */
    case Write = 20;

    /** Moving, renaming and deleting files. */
    case Move = 30;

    /** Whole-tree reference rewriting, once every file has stopped moving. */
    case Reconcile = 40;

    /** Formatting and asset builds. */
    case Format = 50;

    /** Anything that must happen after the project is otherwise final. */
    case Finish = 60;

    public function label(): string
    {
        return match ($this) {
            self::Dependencies => 'Dependencies',
            self::Write => 'Write',
            self::Move => 'Move',
            self::Reconcile => 'Reconcile',
            self::Format => 'Format',
            self::Finish => 'Finish',
        };
    }
}
