<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Plan\Actions;

use Onelegstudios\Refit\Blade\Attribute;
use Onelegstudios\Refit\Blade\Tag;
use Onelegstudios\Refit\Blade\TagRewriter;
use Onelegstudios\Refit\Icons\IconMap;
use Onelegstudios\Refit\Plan\Report;
use Onelegstudios\Refit\Project\Project;

/**
 * Translate icon names across every view, in both the forms Flux accepts.
 */
final class RewriteIconNames extends BladeSweep
{
    /**
     * @param  array<string, string>  $map  Current name mapped to its replacement.
     */
    public function __construct(
        private readonly array $map,
        private readonly string $description,
        private readonly TagRewriter $rewriter = new TagRewriter,
    ) {}

    public function describe(): string
    {
        return sprintf('icons  %s (%d name%s)', $this->description, count($this->map), count($this->map) === 1 ? '' : 's');
    }

    protected function transform(string $source, Project $project, Report $report): string
    {
        // Attribute forms: icon="home", icon-trailing="chevron-down", and the
        // generic <flux:icon name="home" />. The tag is checked as well as the
        // attribute, so the name="email" on every <flux:input> is left alone.
        $source = $this->rewriter->rewriteAttributeValues(
            $source,
            'flux:',
            IconMap::CANDIDATE_ATTRIBUTES,
            function (Tag $tag, Attribute $attribute, string $value): ?string {
                if (! IconMap::namesAnIcon($tag->name, $attribute->name)) {
                    return null;
                }

                return $this->map[$value] ?? null;
            },
        );

        // Tag form: <flux:icon.key />.
        return $this->rewriter->rewriteNameSuffix(
            $source,
            'flux:icon.',
            fn (string $suffix): ?string => $this->map[$suffix] ?? null,
        );
    }
}
