<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan;

/**
 * Renames discovered while the plan is running.
 *
 * Some moves cannot be enumerated at planning time, because an earlier task may
 * still add files to the directory being reorganised. Those actions record what
 * they did here, and the reconcile pass reads the ledger once the tree has
 * settled — which keeps tasks order-independent without making them aware of
 * each other.
 */
final class RenameLedger
{
    /**
     * @var array<string, string>
     */
    private array $renames = [];

    public function record(string $from, string $to): void
    {
        $this->renames[$from] = $to;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->renames;
    }

    public function isEmpty(): bool
    {
        return $this->renames === [];
    }
}
