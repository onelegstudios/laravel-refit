<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Blade;

/**
 * An opening or self-closing Blade component tag.
 */
final class Tag
{
    /**
     * @param  list<Attribute>  $attributes
     */
    public function __construct(
        public readonly string $name,
        public readonly array $attributes,
        public readonly bool $selfClosing,
        public readonly int $offset,
        public readonly int $length,
    ) {}

    public function attribute(string $name): ?Attribute
    {
        foreach ($this->attributes as $attribute) {
            if ($attribute->name === $name) {
                return $attribute;
            }
        }

        return null;
    }

    public function has(string $name): bool
    {
        return $this->attribute($name) instanceof Attribute;
    }

    /**
     * Absolute offset of the tag name, just past the opening angle bracket.
     */
    public function nameOffset(): int
    {
        return $this->offset + 1;
    }

    /**
     * The trailing portion of a dotted tag name, e.g. `home` for `flux:icon.home`.
     */
    public function nameAfter(string $prefix): ?string
    {
        return str_starts_with($this->name, $prefix)
            ? substr($this->name, strlen($prefix))
            : null;
    }
}
