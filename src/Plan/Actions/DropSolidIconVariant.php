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
 * Take `variant="solid"` off the icons that are becoming Lucide.
 *
 * Lucide draws one weight. The override template says so out loud — it throws
 * rather than quietly rendering an outline where a solid was asked for — and
 * that template is the kit's own, which refit copies rather than rewrites.
 *
 * The starter kit asks for exactly that in its two-factor setup modal:
 *
 * ```blade
 * <flux:icon.check x-show="copied" variant="solid" class="text-green-500" />
 * ```
 *
 * So switching to Lucide without this leaves a view that fatals the moment
 * someone copies their setup key. Dropping the attribute falls back to the
 * template's `outline` default, which is the only weight Lucide has.
 *
 * Only usages that actually end up Lucide are touched. An icon refit could not
 * translate keeps its Heroicon, and its solid weight along with it.
 */
final class DropSolidIconVariant extends BladeSweep
{
    /** An icon's own weight, meaningful on the icon component and nowhere else. */
    private const string VARIANT = 'variant';

    /** The weight a component hands down to the icons it renders itself. */
    private const string ICON_VARIANT = 'icon:variant';

    private const string SOLID = 'solid';

    /**
     * @param  list<string>  $names  Icon names, as the views write them today, that are becoming Lucide.
     */
    public function __construct(
        private readonly array $names,
        private readonly TagRewriter $rewriter = new TagRewriter,
    ) {}

    public function describe(): string
    {
        return 'icons  drop variant="solid" (Lucide draws one weight)';
    }

    protected function transform(string $source, Project $project, Report $report): string
    {
        return $this->rewriter->removeAttributes(
            $source,
            'flux:',
            fn (Tag $tag, Attribute $attribute): bool => self::asksForSolid($attribute)
                && $this->drawsLucide($tag, $attribute),
        );
    }

    private function drawsLucide(Tag $tag, Attribute $attribute): bool
    {
        foreach (self::governs($tag, $attribute) as $name) {
            if (in_array($name, $this->names, true)) {
                return true;
            }
        }

        return false;
    }

    private static function asksForSolid(Attribute $attribute): bool
    {
        if (! in_array($attribute->name, [self::VARIANT, self::ICON_VARIANT], true)) {
            return false;
        }

        return ! $attribute->isBound() && $attribute->value === self::SOLID;
    }

    /**
     * The icon names a variant attribute decides the weight of.
     *
     * `variant` belongs to the icon itself, so it only counts on the icon
     * component — every other Flux tag has a `variant` of its own meaning, and
     * `<flux:badge variant="solid">` must survive untouched. `icon:variant` is
     * the pass-through form, and applies to whichever icons its tag names.
     *
     * @return list<string>
     */
    private static function governs(Tag $tag, Attribute $attribute): array
    {
        if ($attribute->name === self::ICON_VARIANT) {
            $names = [];

            foreach (IconMap::NAME_ATTRIBUTES as $candidate) {
                $name = self::literal($tag, $candidate);

                if ($name !== null) {
                    $names[] = $name;
                }
            }

            return $names;
        }

        $suffix = $tag->nameAfter(IconMap::ICON_TAG.'.');

        if ($suffix !== null && $suffix !== '') {
            return [$suffix];
        }

        $name = $tag->name === IconMap::ICON_TAG
            ? self::literal($tag, IconMap::ICON_TAG_ATTRIBUTE)
            : null;

        return $name === null ? [] : [$name];
    }

    /**
     * The literal value of an attribute, or null when it holds an expression
     * rather than a name refit can read.
     */
    private static function literal(Tag $tag, string $name): ?string
    {
        $attribute = $tag->attribute($name);

        if (! $attribute instanceof Attribute || $attribute->isBound() || $attribute->isBoolean()) {
            return null;
        }

        return $attribute->value === '' ? null : $attribute->value;
    }
}
