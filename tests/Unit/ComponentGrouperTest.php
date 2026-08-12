<?php

declare(strict_types=1);

use Onelegstudios\Refit\Project\ComponentGrouper;

beforeEach(function (): void {
    $this->grouper = new ComponentGrouper;
});

it('follows the curated map before anything else', function (): void {
    $targets = $this->grouper->group(
        ['passkey-verify', 'passkey-registration'],
        ['passkey-verify' => 'auth/passkey-verify', 'passkey-registration' => 'auth/passkey-registration'],
        'ui',
    );

    expect($targets)->toBe([
        'passkey-registration' => 'auth/passkey-registration',
        'passkey-verify' => 'auth/passkey-verify',
    ]);
});

it('groups unmapped components by a prefix they share, and drops it', function (): void {
    $targets = $this->grouper->group(['auth-header', 'auth-session-status'], [], 'ui');

    expect($targets)->toBe([
        'auth-header' => 'auth/header',
        'auth-session-status' => 'auth/session-status',
    ]);
});

it('leaves a prefix nothing else shares alone', function (): void {
    $targets = $this->grouper->group(['auth-header', 'placeholder-pattern'], [], 'ui');

    expect($targets)->toBe([
        'auth-header' => 'ui/auth-header',
        'placeholder-pattern' => 'ui/placeholder-pattern',
    ]);
});

it('does not count a mapped component towards a shared prefix', function (): void {
    // `app-logo` is spoken for, so `app-logo-icon` is on its own and stays put.
    $targets = $this->grouper->group(
        ['app-logo', 'app-logo-icon'],
        ['app-logo' => 'brand/logo'],
        'ui',
    );

    expect($targets)->toBe([
        'app-logo' => 'brand/logo',
        'app-logo-icon' => 'ui/app-logo-icon',
    ]);
});

it('keeps a component that is nothing but the prefix out of the folder', function (): void {
    // Stripping `auth` from `auth` would leave no filename at all.
    $targets = $this->grouper->group(['auth', 'auth-header', 'auth-session-status'], [], 'ui');

    expect($targets)->toBe([
        'auth' => 'ui/auth',
        'auth-header' => 'auth/header',
        'auth-session-status' => 'auth/session-status',
    ]);
});

it('sends a component with no prefix at all to the fallback', function (): void {
    expect($this->grouper->group(['head'], [], 'ui'))->toBe(['head' => 'ui/head']);
});
