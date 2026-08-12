<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan;

use Onelegstudios\Refit\Blade\TagParser;

/**
 * Checks that a rewrite left the Blade structurally intact.
 *
 * The check is differential. The starter kit itself ships tags that do not
 * balance by name — the two-factor setup modal writes
 * `<flux:icon.document-duplicate ...></flux:icon>` — so an absolute check would
 * reject files refit never broke.
 *
 * Comparison is on the imbalance itself, not on the message, because a rename is
 * expected to change the tag name inside a pre-existing problem. Renaming that
 * modal's icon to `copy` leaves exactly the imbalance it started with, and must
 * not be reported as new.
 *
 * Compiling the Blade would be a stronger check, but it needs a booted view
 * factory and rejects perfectly good starter kit views without one.
 */
final class BladeGuard
{
    /**
     * @param  list<string>  $prefixes
     */
    public function __construct(
        private readonly array $prefixes = ['flux:', 'x-'],
        private readonly TagParser $parser = new TagParser,
    ) {}

    /**
     * Problems the rewrite introduced, as human-readable lines.
     *
     * @return list<string>
     */
    public function check(string $before, string $after): array
    {
        $existing = [];

        foreach ($this->problems($before) as $problem) {
            $delta = $problem['opens'] - $problem['closes'];

            $existing[$delta] = ($existing[$delta] ?? 0) + 1;
        }

        $new = [];

        foreach ($this->problems($after) as $problem) {
            $delta = $problem['opens'] - $problem['closes'];

            if (($existing[$delta] ?? 0) > 0) {
                $existing[$delta]--;

                continue;
            }

            $new[] = sprintf(
                '<%s> opens %d time(s) but closes %d time(s)',
                $problem['name'],
                $problem['opens'],
                $problem['closes'],
            );
        }

        return $new;
    }

    /**
     * @return list<array{name: string, opens: int, closes: int}>
     */
    private function problems(string $source): array
    {
        $problems = [];

        foreach ($this->prefixes as $prefix) {
            $counts = [];

            foreach ($this->parser->parse($source, $prefix) as $tag) {
                if ($tag->selfClosing) {
                    continue;
                }

                $counts[$tag->name] ??= ['opens' => 0, 'closes' => 0];
                $counts[$tag->name]['opens']++;
            }

            // Closing tags are counted independently, so a rename that moved one
            // end of a pair shows up as both an unclosed opener and an orphaned
            // closer rather than slipping through on a matching total.
            if (preg_match_all('/<\/('.preg_quote($prefix, '/').'[A-Za-z0-9_\-.:]*)\s*>/', $source, $matches) > 0) {
                foreach ($matches[1] as $name) {
                    $counts[$name] ??= ['opens' => 0, 'closes' => 0];
                    $counts[$name]['closes']++;
                }
            }

            foreach ($counts as $name => $count) {
                if ($count['opens'] !== $count['closes']) {
                    $problems[] = [
                        'name' => (string) $name,
                        'opens' => $count['opens'],
                        'closes' => $count['closes'],
                    ];
                }
            }
        }

        return $problems;
    }
}
