<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Blade;

/**
 * Finds Blade component tags in a template.
 *
 * A character scanner rather than a regex, because the starter kit views contain
 * exactly the cases a regex gets wrong: attributes spread over several lines, and
 * bound values holding expressions with angle brackets and nested quotes.
 *
 * Escaped quotes inside an attribute value are not supported — no starter kit
 * view uses them, and handling them would mean tracking Blade's own escaping.
 */
final class TagParser
{
    private const string NAME_CHARACTERS = '/[A-Za-z0-9_\-.:]/';

    private const string ATTRIBUTE_NAME_CHARACTERS = '/[A-Za-z0-9_\-.:@]/';

    private const array WHITESPACE = [' ', "\t", "\n", "\r"];

    /**
     * Parse every opening and self-closing tag whose name starts with $prefix.
     *
     * Closing tags are not returned; use {@see TagRewriter::rename()} when both
     * ends of a paired tag need to move together.
     *
     * @return list<Tag>
     */
    public function parse(string $source, string $prefix): array
    {
        $tags = [];
        $length = strlen($source);
        $cursor = 0;

        while (($start = strpos($source, '<'.$prefix, $cursor)) !== false) {
            $nameStart = $start + 1;
            $nameEnd = $nameStart;

            while ($nameEnd < $length && preg_match(self::NAME_CHARACTERS, $source[$nameEnd]) === 1) {
                $nameEnd++;
            }

            // The name has to end at a boundary, otherwise `<flux:input` matched
            // the leading edge of something longer that merely starts the same.
            if ($nameEnd >= $length || ! $this->isNameBoundary($source[$nameEnd])) {
                $cursor = $nameStart;

                continue;
            }

            $tag = $this->scanAttributes($source, $nameEnd);

            if ($tag === null) {
                $cursor = $nameStart;

                continue;
            }

            [$attributes, $end, $selfClosing] = $tag;

            $tags[] = new Tag(
                name: substr($source, $nameStart, $nameEnd - $nameStart),
                attributes: $attributes,
                selfClosing: $selfClosing,
                offset: $start,
                length: $end - $start,
            );

            $cursor = $end;
        }

        return $tags;
    }

    private function isNameBoundary(string $character): bool
    {
        return in_array($character, self::WHITESPACE, true)
            || $character === '/'
            || $character === '>';
    }

    /**
     * Walk the attribute list and find where the tag closes.
     *
     * @return array{list<Attribute>, int, bool}|null
     */
    private function scanAttributes(string $source, int $cursor): ?array
    {
        $length = strlen($source);
        $attributes = [];

        while ($cursor < $length) {
            $character = $source[$cursor];

            if (in_array($character, self::WHITESPACE, true)) {
                $cursor++;

                continue;
            }

            if ($character === '>') {
                return [$attributes, $cursor + 1, false];
            }

            if ($character === '/' && $cursor + 1 < $length && $source[$cursor + 1] === '>') {
                return [$attributes, $cursor + 2, true];
            }

            $attribute = $this->scanAttribute($source, $cursor);

            if ($attribute === false) {
                // An attribute value opened a quote that never closes, so where
                // this tag ends is anyone's guess. Refuse to report it rather
                // than hand a rewriter a span that might be wrong.
                return null;
            }

            if ($attribute === null) {
                // Not an attribute at all — a Blade echo or directive sitting in
                // the attribute list. Step over it and keep reading the tag.
                $cursor++;

                continue;
            }

            $attributes[] = $attribute;
            $cursor = $attribute->offset + $attribute->length;
        }

        return null;
    }

    /**
     * @return Attribute|null|false Null to skip this character, false to abandon
     *                              the tag because its extent is unknowable.
     */
    private function scanAttribute(string $source, int $offset): Attribute|null|false
    {
        $length = strlen($source);
        $cursor = $offset;

        while ($cursor < $length && preg_match(self::ATTRIBUTE_NAME_CHARACTERS, $source[$cursor]) === 1) {
            $cursor++;
        }

        if ($cursor === $offset) {
            return null;
        }

        $name = substr($source, $offset, $cursor - $offset);

        if ($cursor >= $length || $source[$cursor] !== '=') {
            return new Attribute($name, null, '', $offset, $cursor - $offset);
        }

        $cursor++;

        if ($cursor >= $length) {
            return false;
        }

        $quote = $source[$cursor];

        if ($quote === '"' || $quote === "'") {
            $close = strpos($source, $quote, $cursor + 1);

            if ($close === false) {
                return false;
            }

            return new Attribute(
                name: $name,
                value: substr($source, $cursor + 1, $close - $cursor - 1),
                quote: $quote,
                offset: $offset,
                length: $close + 1 - $offset,
            );
        }

        $valueStart = $cursor;

        while ($cursor < $length
            && ! in_array($source[$cursor], self::WHITESPACE, true)
            && $source[$cursor] !== '>') {
            $cursor++;
        }

        return new Attribute(
            name: $name,
            value: substr($source, $valueStart, $cursor - $valueStart),
            quote: '',
            offset: $offset,
            length: $cursor - $offset,
        );
    }
}
