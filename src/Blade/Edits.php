<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Blade;

use InvalidArgumentException;

/**
 * A batch of offset-based replacements applied to a string in one pass.
 *
 * Collecting edits and applying them back-to-front is what lets several rewrites
 * target the same file without any of them invalidating the offsets the others
 * were built from.
 */
final class Edits
{
    /**
     * @var list<array{offset: int, length: int, text: string}>
     */
    private array $edits = [];

    public function replace(int $offset, int $length, string $text): void
    {
        if ($offset < 0 || $length < 0) {
            throw new InvalidArgumentException('Edit offset and length must not be negative.');
        }

        $this->edits[] = ['offset' => $offset, 'length' => $length, 'text' => $text];
    }

    public function isEmpty(): bool
    {
        return $this->edits === [];
    }

    public function count(): int
    {
        return count($this->edits);
    }

    public function apply(string $source): string
    {
        $edits = $this->edits;

        usort($edits, fn (array $a, array $b): int => $b['offset'] <=> $a['offset']);

        foreach ($edits as $edit) {
            $source = substr_replace($source, $edit['text'], $edit['offset'], $edit['length']);
        }

        return $source;
    }
}
