<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Tasks;

/**
 * Drop the auth layouts the application does not use.
 *
 * The kit ships three — card, simple and split — and `layouts/auth.blade.php`
 * delegates to exactly one of them. The other two are dead weight from the first
 * commit onwards.
 */
final class KeepOneAuthLayout extends KeepOneLayout
{
    protected function family(): string
    {
        return 'auth';
    }
}
