<?php

declare(strict_types=1);

use Onelegstudios\Refit\Plan\BladeGuard;

beforeEach(function (): void {
    $this->guard = new BladeGuard;
});

it('passes a rewrite that keeps the structure', function (): void {
    expect($this->guard->check(
        '<x-app-logo>slot</x-app-logo>',
        '<x-ui.app-logo>slot</x-ui.app-logo>',
    ))->toBe([]);
});

it('catches a rename that moved only the opening tag', function (): void {
    $problems = $this->guard->check(
        '<x-app-logo>slot</x-app-logo>',
        '<x-ui.app-logo>slot</x-app-logo>',
    );

    expect($problems)->toHaveCount(2);
});

it('ignores an imbalance the file already had', function (): void {
    $before = '<flux:icon.document-duplicate x-show="!copied"></flux:icon>';

    expect($this->guard->check($before, $before))->toBe([]);
});

it('ignores a pre-existing imbalance that a rename carried along', function (): void {
    // The starter kit's two-factor modal really does write this. Renaming the
    // icon changes the tag name inside a problem that was already there, and
    // must not be reported as something refit broke.
    expect($this->guard->check(
        '<flux:icon.document-duplicate x-show="!copied"></flux:icon>',
        '<flux:icon.copy x-show="!copied"></flux:icon>',
    ))->toBe([]);
});

it('still reports a new imbalance in a file that already had one', function (): void {
    $problems = $this->guard->check(
        '<flux:icon.document-duplicate></flux:icon>',
        '<flux:icon.copy></flux:icon><x-card>unclosed',
    );

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toContain('x-card');
});
