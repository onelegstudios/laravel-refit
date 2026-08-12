<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Support;

use Symfony\Component\Process\Process;

/**
 * The clean-working-tree precondition.
 *
 * Refit rewrites files in place and the changes are one-way. Requiring a clean
 * tree means `git checkout .` is always the undo, which is why refit ships no
 * backup or rollback machinery of its own.
 */
final class Git
{
    public function __construct(private readonly string $root) {}

    public function isRepository(): bool
    {
        return $this->run(['git', 'rev-parse', '--is-inside-work-tree']) !== null;
    }

    public function isClean(): bool
    {
        return $this->run(['git', 'status', '--porcelain']) === '';
    }

    /**
     * Trimmed stdout, or null when the command failed.
     *
     * @param  list<string>  $command
     */
    private function run(array $command): ?string
    {
        $process = new Process($command, $this->root, timeout: 30);

        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }
}
