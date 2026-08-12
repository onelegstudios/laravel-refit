<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Project;

/**
 * Decide which subfolder each loose anonymous component belongs in.
 *
 * Pure name arithmetic, no filesystem: given the bare component names it hands
 * back a `name => folder/name` map. Three layers, most specific first.
 *
 * The curated map carries the judgement calls for the components the starter kit
 * actually ships — `passkey-verify` reads as part of the auth flow, so it lands
 * under `auth/` rather than in a folder of its own. Anything the map has no
 * opinion about falls to a prefix rule: a leading token two or more components
 * share becomes their folder, and is dropped from the filename now that the
 * folder says it. What is left has nothing to group with and goes in the
 * fallback folder.
 */
final class ComponentGrouper
{
    /**
     * @param  list<string>  $names  bare component names, without the extension
     * @param  array<string, string>  $groups  name => `folder/name`
     * @return array<string, string> name => `folder/name`
     */
    public function group(array $names, array $groups, string $fallback): array
    {
        sort($names);

        $ungrouped = array_values(array_filter(
            $names,
            static fn (string $name): bool => ! isset($groups[$name]),
        ));

        $shared = $this->sharedPrefixes($ungrouped);
        $targets = [];

        foreach ($names as $name) {
            $targets[$name] = $groups[$name]
                ?? $this->byPrefix($name, $shared)
                ?? $fallback.'/'.$name;
        }

        return $targets;
    }

    /**
     * `auth-header` under a shared `auth` prefix becomes `auth/header`.
     *
     * A name that is nothing but the prefix has no filename left once the folder
     * takes it, so it is left for the fallback.
     *
     * @param  list<string>  $shared
     */
    private function byPrefix(string $name, array $shared): ?string
    {
        $parts = explode('-', $name, 2);

        if (count($parts) !== 2 || $parts[1] === '') {
            return null;
        }

        return in_array($parts[0], $shared, true)
            ? $parts[0].'/'.$parts[1]
            : null;
    }

    /**
     * Leading tokens carried by two or more of the given names.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function sharedPrefixes(array $names): array
    {
        $counts = [];

        foreach ($names as $name) {
            $parts = explode('-', $name, 2);

            if (count($parts) !== 2 || $parts[1] === '') {
                continue;
            }

            $counts[$parts[0]] = ($counts[$parts[0]] ?? 0) + 1;
        }

        return array_keys(array_filter(
            $counts,
            static fn (int $count): bool => $count > 1,
        ));
    }
}
