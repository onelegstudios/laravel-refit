<?php

declare(strict_types=1);

use Onelegstudios\Refit\Refit;

it('resolves the singleton', function () {
    expect(app(Refit::class))->toBeInstanceOf(Refit::class);
});

it('returns the same instance from the container', function () {
    expect(app(Refit::class))->toBe(app(Refit::class));
});

it('merges the package config', function () {
    expect(config('refit.placeholder'))->toBe('default');
});

it('loads the package views', function () {
    expect(view()->exists('refit::placeholder'))->toBeTrue();
});

it('registers the artisan command', function () {
    $this->artisan('refit:placeholder')
        ->expectsOutputToContain('Refit placeholder command executed.')
        ->assertSuccessful();
});
