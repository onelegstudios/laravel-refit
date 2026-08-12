<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Project;

/**
 * How the starter kit lays out its Livewire components.
 */
enum ComponentStyle: string
{
    /**
     * Single-file components under resources/views/pages, prefixed with an emoji.
     */
    case SingleFile = 'single-file';

    /**
     * Livewire classes under app/Livewire with views under resources/views/livewire.
     */
    case ClassBased = 'class-based';

    public function label(): string
    {
        return match ($this) {
            self::SingleFile => 'single-file components',
            self::ClassBased => 'class components',
        };
    }

    /**
     * The directory holding the kit's Livewire views, relative to the project root.
     */
    public function viewDirectory(): string
    {
        return match ($this) {
            self::SingleFile => 'resources/views/pages',
            self::ClassBased => 'resources/views/livewire',
        };
    }
}
