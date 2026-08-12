<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Project;

/**
 * Optional starter kit features that survive into the installed application.
 *
 * The Livewire kit ships every feature and lets `install:features` chisel the
 * unwanted ones away, so refit has to discover what is left rather than assume.
 */
enum Feature: string
{
    case Teams = 'teams';
    case WorkOs = 'workos';
    case Passkeys = 'passkeys';
    case TwoFactor = 'two-factor';
    case Registration = 'registration';

    public function label(): string
    {
        return match ($this) {
            self::Teams => 'Teams',
            self::WorkOs => 'WorkOS AuthKit',
            self::Passkeys => 'Passkeys',
            self::TwoFactor => 'Two-factor authentication',
            self::Registration => 'Registration',
        };
    }
}
