<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Icons;

/**
 * What to do about the starter kit's mixed icon set.
 *
 * Flux ships Heroicons and resolves them by bare name. The Livewire kit then
 * vendors a handful of Lucide icons in as `resources/views/flux/icon/*.blade.php`
 * overrides, because those names have no Heroicons equivalent — so a fresh
 * install is already speaking two icon sets at once.
 */
enum IconStrategy: string
{
    /** Leave the mix alone. */
    case Keep = 'keep';

    /** Drop the Lucide overrides and map their usages onto Heroicons. */
    case Heroicons = 'heroicons';

    /** Move everything to Lucide, including the icons Flux renders internally. */
    case Lucide = 'lucide';

    public function label(): string
    {
        return match ($this) {
            self::Keep => 'Keep the current mix of Heroicons and Lucide',
            self::Heroicons => 'Heroicons only',
            self::Lucide => 'Lucide only',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Keep => 'Changes nothing — what a fresh starter kit gives you',
            self::Heroicons => 'Removes the vendored Lucide overrides, uses what Flux ships',
            self::Lucide => 'Generates Flux icon overrides so even Flux\'s own internals match',
        };
    }
}
