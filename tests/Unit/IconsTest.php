<?php

declare(strict_types=1);

use Onelegstudios\Refit\Icons\FluxInternals;
use Onelegstudios\Refit\Icons\IconMap;
use Onelegstudios\Refit\Icons\IconScanner;
use Onelegstudios\Refit\Icons\OverrideGenerator;

it('bundles artwork for every Lucide name it can translate to', function (): void {
    $generator = new OverrideGenerator;

    $missing = array_values(array_filter(
        array_unique([...array_values(IconMap::HEROICONS_TO_LUCIDE), ...array_values(IconMap::FLUX_OWNED)]),
        fn (string $lucide): bool => ! $generator->has($lucide),
    ));

    expect($missing)->toBe([]);
});

it('bundles artwork for the Lucide icons the kit vendors in', function (): void {
    $generator = new OverrideGenerator;

    $missing = array_values(array_filter(
        array_keys(IconMap::LUCIDE_TO_HEROICONS),
        fn (string $lucide): bool => ! $generator->has($lucide),
    ));

    expect($missing)->toBe([]);
});

it('can translate every icon Flux is recorded as rendering internally', function (): void {
    foreach (FluxInternals::names() as $name) {
        expect(IconMap::toLucide($name))->not->toBeNull("no Lucide name for [{$name}]");
    }
});

it('bundles artwork for every recorded Flux internal', function (): void {
    $generator = new OverrideGenerator;

    $missing = array_values(array_filter(
        FluxInternals::names(),
        fn (string $name): bool => ! $generator->has((string) IconMap::toLucide($name)),
    ));

    expect($missing)->toBe([]);
});

it('records the Pro names contributors cannot scan for themselves', function (): void {
    // flux-pro is licensed, so most checkouts have nothing to scan. The manifest
    // is the only place those names exist here — an empty block means a run of
    // bin/scan-flux-internals.php dropped them.
    expect(FluxInternals::namesFor('livewire/flux-pro'))->not->toBe([]);
});

it('treats name as an icon only on the generic icon tag', function (): void {
    expect(IconMap::namesAnIcon('flux:icon', 'name'))->toBeTrue()
        ->and(IconMap::namesAnIcon('flux:input', 'name'))->toBeFalse()
        ->and(IconMap::namesAnIcon('flux:button', 'icon-trailing'))->toBeTrue();
});

it('never treats the variant keyword as an icon name', function (): void {
    expect(IconMap::namesAnIcon('flux:button', 'icon:variant'))->toBeFalse()
        ->and(IconMap::namesAnIcon('flux:icon', 'variant'))->toBeFalse();
});

it('translates the Flux spinner without renaming it', function (): void {
    expect(IconMap::isFluxOwned('loading'))->toBeTrue()
        ->and(IconMap::toLucide('loading'))->toBe('loader-circle')
        ->and(IconMap::isFluxOwned('envelope'))->toBeFalse();
});

it('keeps the spinner spinning', function (): void {
    $rendered = (new OverrideGenerator)->render('loader-circle', overrideName: 'loading');

    expect($rendered)
        ->toContain("Flux::classes('shrink-0 animate-spin')")
        ->toContain('overriding the icon Flux draws itself');
});

it('finds all three forms an icon name is written in', function (): void {
    $names = (new IconScanner)->scanSource(
        '<flux:icon.key /><flux:button icon="plus" icon:variant="outline" />'
        .'<flux:icon name="users" /><flux:input name="email" />',
    );

    sort($names);

    expect($names)->toBe(['key', 'plus', 'users']);
});

it('ignores an icon name a component interpolates at render time', function (): void {
    // Flux Pro passes its own prop through unbound, which the bound-value check
    // never sees: `{{ $icon }}` is not a name refit can translate.
    $names = (new IconScanner)->scanSource(
        '<flux:icon name="{{ $icon }}" variant="{{ $iconVariant }}" />'
        .'<flux:button icon="{!! $raw !!}" /><flux:icon.check />',
    );

    expect($names)->toBe(['check']);
});

it('finds an icon a component only names as a prop default', function (): void {
    $names = (new IconScanner)->scanPropDefaults(
        "@props([\n    'icon' => 'exclamation-triangle',\n    'icon-trailing' => 'chevron-down',\n"
        ."    'name' => null,\n    'message' => 'x-mark',\n])",
    );

    sort($names);

    expect($names)->toBe(['chevron-down', 'exclamation-triangle']);
});

it('renders an override matching the template the kit already uses', function (): void {
    $rendered = (new OverrideGenerator)->render('house');

    expect($rendered)
        ->toContain('{{-- Credit: Lucide (https://lucide.dev) --}}')
        ->toContain("'variant' => 'outline',")
        ->toContain("Flux::classes('shrink-0')")
        ->toContain('data-flux-icon')
        ->toContain('viewBox="0 0 24 24"')
        ->toContain('<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/>')
        ->not->toContain('__PATHS__');
});

it('notes why an alias override exists', function (): void {
    $rendered = (new OverrideGenerator)->render('x', overrideName: 'x-mark');

    expect($rendered)->toContain('overriding the Heroicons name Flux resolves internally');
});

it('refuses to render an icon it does not bundle', function (): void {
    (new OverrideGenerator)->render('definitely-not-an-icon');
})->throws(RuntimeException::class, 'does not bundle');
