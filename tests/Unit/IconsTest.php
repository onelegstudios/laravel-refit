<?php

declare(strict_types=1);

use Onelegstudios\Refit\Icons\IconMap;
use Onelegstudios\Refit\Icons\IconScanner;
use Onelegstudios\Refit\Icons\OverrideGenerator;

it('bundles artwork for every Lucide name it can translate to', function (): void {
    $generator = new OverrideGenerator;

    $missing = array_values(array_filter(
        array_unique(array_values(IconMap::HEROICONS_TO_LUCIDE)),
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

it('can translate every icon Flux is assumed to render internally', function (): void {
    foreach (IconMap::FLUX_INTERNAL_FALLBACK as $name) {
        expect(IconMap::toLucide($name))->not->toBeNull("no Lucide name for [{$name}]");
    }
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

it('leaves the Flux spinner alone', function (): void {
    expect(IconMap::isReserved('loading'))->toBeTrue()
        ->and(IconMap::toLucide('loading'))->toBeNull();
});

it('finds all three forms an icon name is written in', function (): void {
    $names = (new IconScanner)->scanSource(
        '<flux:icon.key /><flux:button icon="plus" icon:variant="outline" />'
        .'<flux:icon name="users" /><flux:input name="email" />',
    );

    sort($names);

    expect($names)->toBe(['key', 'plus', 'users']);
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
    $rendered = (new OverrideGenerator)->render('x', alias: 'x');

    expect($rendered)->toContain('overriding the Heroicons name Flux resolves internally');
});

it('refuses to render an icon it does not bundle', function (): void {
    (new OverrideGenerator)->render('definitely-not-an-icon');
})->throws(RuntimeException::class, 'does not bundle');
