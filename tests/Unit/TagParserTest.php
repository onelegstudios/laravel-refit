<?php

declare(strict_types=1);

use Onelegstudios\Refit\Blade\TagParser;

beforeEach(function (): void {
    $this->parser = new TagParser;
});

it('parses a self-closing tag with mixed attribute kinds', function (): void {
    $tags = $this->parser->parse(
        '<flux:input name="email" :label="__(\'Email\')" required />',
        'flux:',
    );

    expect($tags)->toHaveCount(1)
        ->and($tags[0]->name)->toBe('flux:input')
        ->and($tags[0]->selfClosing)->toBeTrue()
        ->and($tags[0]->attribute('name')?->value)->toBe('email')
        ->and($tags[0]->attribute(':label')?->value)->toBe("__('Email')")
        ->and($tags[0]->attribute('required')?->isBoolean())->toBeTrue();
});

it('keeps bound expressions containing quotes and angle brackets intact', function (): void {
    $source = '<flux:sidebar.item :current="request()->routeIs(\'dashboard\')" :wide="$a > $b">X</flux:sidebar.item>';

    $tags = $this->parser->parse($source, 'flux:');

    expect($tags)->toHaveCount(1)
        ->and($tags[0]->selfClosing)->toBeFalse()
        ->and($tags[0]->attribute(':current')?->value)->toBe("request()->routeIs('dashboard')")
        ->and($tags[0]->attribute(':wide')?->value)->toBe('$a > $b');
});

it('parses attributes spread over several lines', function (): void {
    $tags = $this->parser->parse(
        "<flux:button\n    variant=\"primary\"\n    icon:trailing=\"chevron-down\"\n>Go</flux:button>",
        'flux:',
    );

    expect($tags[0]->attribute('variant')?->value)->toBe('primary')
        ->and($tags[0]->attribute('icon:trailing')?->value)->toBe('chevron-down');
});

it('does not match a tag that merely starts with the prefix', function (): void {
    $tags = $this->parser->parse('<x-app-logo-icon /> <x-app-logo />', 'x-app-logo');

    expect(array_map(fn ($tag): string => $tag->name, $tags))
        ->toBe(['x-app-logo-icon', 'x-app-logo']);
});

it('reads the dotted suffix off a namespaced tag', function (): void {
    $tags = $this->parser->parse('<flux:icon.chevron-down class="size-4" />', 'flux:');

    expect($tags[0]->nameAfter('flux:icon.'))->toBe('chevron-down')
        ->and($tags[0]->nameAfter('flux:input'))->toBeNull();
});

it('reports offsets that address the tag inside the source', function (): void {
    $source = 'before <flux:input required /> after';

    $tags = $this->parser->parse($source, 'flux:');

    expect(substr($source, $tags[0]->offset, $tags[0]->length))->toBe('<flux:input required />');
});

it('steps over an unterminated tag without losing later ones', function (): void {
    $tags = $this->parser->parse('<flux:input value="unclosed <flux:button />', 'flux:');

    expect($tags)->toHaveCount(1)
        ->and($tags[0]->name)->toBe('flux:button');
});

it('finds every flux tag in each starter kit fixture', function (string $kit): void {
    $root = requireFixture($kit);
    $parsed = 0;

    foreach (glob($root.'/resources/views/**/*.blade.php') ?: [] as $path) {
        $parsed += count($this->parser->parse((string) file_get_contents($path), 'flux:'));
    }

    // A grep for the same opening tags is the independent second opinion.
    $grep = (int) shell_exec(sprintf(
        "grep -rhoE '<flux:[a-zA-Z0-9._-]+' %s/resources/views | wc -l",
        escapeshellarg($root),
    ));

    $all = 0;

    foreach (starterKitBlades($root) as $path) {
        $all += count($this->parser->parse((string) file_get_contents($path), 'flux:'));
    }

    expect($all)->toBe($grep)->and($parsed)->toBeGreaterThan(0);
})->with(starterKits());

/**
 * @return list<string>
 */
function starterKitBlades(string $root): array
{
    $paths = [];

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/resources/views', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($items as $item) {
        if ($item->isFile() && str_ends_with($item->getFilename(), '.blade.php')) {
            $paths[] = $item->getPathname();
        }
    }

    return $paths;
}
