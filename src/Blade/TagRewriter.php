<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Blade;

/**
 * Targeted rewrites over Blade component tags.
 *
 * Every method is a pure string transform so the whole rewriting surface can be
 * unit tested against inline snippets, with no filesystem and no application.
 */
final class TagRewriter
{
    public function __construct(private readonly TagParser $parser = new TagParser) {}

    /**
     * Rename a component tag, both its opening and closing forms.
     *
     * `<x-app-logo :sidebar="true" />` and the matching `</x-app-logo>` move
     * together, and no other tag that merely starts with the same characters is
     * touched.
     */
    public function rename(string $source, string $from, string $to): string
    {
        $edits = new Edits;

        foreach ($this->parser->parse($source, $from) as $tag) {
            if ($tag->name !== $from) {
                continue;
            }

            $edits->replace($tag->nameOffset(), strlen($from), $to);
        }

        $pattern = '/<\/'.preg_quote($from, '/').'(\s*)>/';

        $source = $edits->apply($source);

        $replaced = preg_replace($pattern, '</'.$to.'$1>', $source);

        return $replaced ?? $source;
    }

    /**
     * Rewrite the literal value of named attributes on tags under a prefix.
     *
     * The callback receives the tag, the attribute, and its current value, and
     * returns a replacement or null to leave it alone. Bound and boolean
     * attributes are skipped: their value is a PHP expression, not a literal.
     *
     * @param  list<string>  $names
     * @param  callable(Tag, Attribute, string): (string|null)  $callback
     */
    public function rewriteAttributeValues(
        string $source,
        string $prefix,
        array $names,
        callable $callback,
    ): string {
        $edits = new Edits;

        foreach ($this->parser->parse($source, $prefix) as $tag) {
            foreach ($tag->attributes as $attribute) {
                if (! in_array($attribute->name, $names, true)) {
                    continue;
                }

                if ($attribute->isBound() || $attribute->isBoolean() || $attribute->quote === '') {
                    continue;
                }

                $value = (string) $attribute->value;
                $replacement = $callback($tag, $attribute, $value);

                if ($replacement === null || $replacement === $value) {
                    continue;
                }

                $edits->replace($attribute->valueOffset(), $attribute->valueLength(), $replacement);
            }
        }

        return $edits->apply($source);
    }

    /**
     * Add an attribute to every tag with the given name that lacks it.
     *
     * A tag that already names the attribute — literally or bound — is left
     * alone: the value sitting there is a decision the application has already
     * made, and overruling it would be a rewrite the user did not ask for.
     */
    public function addAttribute(string $source, string $name, string $attribute, string $value): string
    {
        $edits = new Edits;

        foreach ($this->parser->parse($source, $name) as $tag) {
            if ($tag->name !== $name) {
                continue;
            }

            if ($tag->has($attribute) || $tag->has(':'.$attribute)) {
                continue;
            }

            $edits->replace(
                $tag->nameOffset() + strlen($name),
                0,
                sprintf(' %s="%s"', $attribute, $value),
            );
        }

        return $edits->apply($source);
    }

    /**
     * Rewrite the dotted suffix of tags such as `<flux:icon.home />`.
     *
     * @param  callable(string): (string|null)  $callback
     */
    public function rewriteNameSuffix(string $source, string $prefix, callable $callback): string
    {
        $edits = new Edits;

        foreach ($this->parser->parse($source, $prefix) as $tag) {
            $suffix = $tag->nameAfter($prefix);

            if ($suffix === null || $suffix === '') {
                continue;
            }

            $replacement = $callback($suffix);

            if ($replacement === null || $replacement === $suffix) {
                continue;
            }

            $edits->replace(
                $tag->nameOffset() + strlen($prefix),
                strlen($suffix),
                $replacement,
            );
        }

        return $edits->apply($source);
    }
}
