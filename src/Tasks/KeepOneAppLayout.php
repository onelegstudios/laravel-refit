<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Tasks;

/**
 * Drop the app layouts the application does not use.
 *
 * The kit ships two shells — sidebar and header — and `layouts/app.blade.php`
 * delegates to exactly one of them. The other is a whole navigation chrome the
 * application never renders, kept in step with the one it does by hand.
 */
final class KeepOneAppLayout extends KeepOneLayout
{
    protected function family(): string
    {
        return 'app';
    }
}
