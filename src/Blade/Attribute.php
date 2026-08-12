<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Blade;

/**
 * A single attribute parsed off a Blade component tag.
 *
 * Offsets are absolute within the source the tag was parsed from, so a rewriter
 * can splice a new value in without re-rendering the surrounding tag.
 */
final class Attribute
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $value,
        public readonly string $quote,
        public readonly int $offset,
        public readonly int $length,
    ) {}

    /**
     * A boolean attribute — present with no value, such as `required` or `viewable`.
     */
    public function isBoolean(): bool
    {
        return $this->value === null;
    }

    /**
     * Bound attributes take a PHP expression rather than a literal, so their
     * value is never a name refit can translate.
     */
    public function isBound(): bool
    {
        return str_starts_with($this->name, ':');
    }

    /**
     * Absolute offset of the value text, excluding the surrounding quotes.
     */
    public function valueOffset(): int
    {
        return $this->offset
            + strlen($this->name)
            + 1
            + strlen($this->quote);
    }

    public function valueLength(): int
    {
        return strlen($this->value ?? '');
    }
}
