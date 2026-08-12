<?php

declare(strict_types=1);

use Onelegstudios\Refit\Project\Project;
use Onelegstudios\Refit\Project\ProjectDetector;
use Onelegstudios\Refit\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * The starter kit variations `composer fixtures` downloads.
 *
 * @return list<string>
 */
function starterKits(): array
{
    return [
        'livewire',
        'livewire-class-components',
        'livewire-teams',
        'livewire-workos',
        'livewire-workos-teams',
    ];
}

function fixturePath(string $kit): string
{
    return dirname(__DIR__).'/tests/fixtures/starter-kits/'.$kit;
}

/**
 * Fixtures are gitignored, so any test needing one skips when it is absent.
 * Run `composer fixtures` to download them.
 */
function requireFixture(string $kit): string
{
    $path = fixturePath($kit);

    if (! is_dir($path)) {
        test()->markTestSkipped("Starter kit fixture [{$kit}] is missing — run `composer fixtures`.");
    }

    return $path;
}

/**
 * A throwaway copy of a fixture, cleaned up when the test process ends.
 */
function copyFixture(string $kit): string
{
    $source = requireFixture($kit);
    $destination = sys_get_temp_dir().'/refit-'.$kit.'-'.bin2hex(random_bytes(6));

    copyDirectory($source, $destination);

    register_shutdown_function(static fn () => deleteDirectory($destination));

    return $destination;
}

function detectFixture(string $kit): Project
{
    return (new ProjectDetector)->detect(requireFixture($kit));
}

function copyDirectory(string $source, string $destination): void
{
    mkdir($destination, 0755, true);

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($items as $item) {
        $target = $destination.DIRECTORY_SEPARATOR.$items->getSubPathname();

        $item->isDir() ? mkdir($target, 0755, true) : copy($item->getPathname(), $target);
    }
}

function deleteDirectory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}
