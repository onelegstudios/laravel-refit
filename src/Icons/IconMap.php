<?php

declare(strict_types=1);

namespace Onelegstudios\Refit\Icons;

/**
 * Curated translations between the icon sets the starter kit uses.
 *
 * These are semantic, not mechanical: Heroicons' `arrow-right-start-on-rectangle`
 * is Lucide's `log-out`, and `magnifying-glass` is `search`. The set covers every
 * name the five starter kit variants reference, plus the ones Flux's own stubs
 * render. Anything outside it is reported rather than guessed at.
 */
final class IconMap
{
    /**
     * Attributes that carry an icon name on any Flux component.
     *
     * `icon:variant` is deliberately absent — it takes an appearance keyword such
     * as `outline`, not a name, and translating it would corrupt the tag.
     *
     * @var list<string>
     */
    public const array NAME_ATTRIBUTES = [
        'icon',
        'icon-leading',
        'icon-trailing',
        'icon:leading',
        'icon:trailing',
    ];

    /**
     * The generic icon component, which names its icon through `name`.
     *
     * The kit writes all three forms: `<flux:icon.key />`, `icon="key"`, and
     * `<flux:icon name="key" />`.
     */
    public const string ICON_TAG = 'flux:icon';

    /**
     * The attribute {@see ICON_TAG} uses, which is a name *only* on that tag.
     *
     * Treating `name` as an icon everywhere would rewrite the `name="email"` on
     * every `<flux:input>` in the kit.
     */
    public const string ICON_TAG_ATTRIBUTE = 'name';

    /**
     * Every attribute worth parsing, before the per-tag rules are applied.
     *
     * @var list<string>
     */
    public const array CANDIDATE_ATTRIBUTES = [
        'icon',
        'icon-leading',
        'icon-trailing',
        'icon:leading',
        'icon:trailing',
        'name',
    ];

    /**
     * Does this attribute name an icon, given the tag it sits on?
     */
    public static function namesAnIcon(string $tag, string $attribute): bool
    {
        if (in_array($attribute, self::NAME_ATTRIBUTES, true)) {
            return true;
        }

        return $tag === self::ICON_TAG && $attribute === self::ICON_TAG_ATTRIBUTE;
    }

    /**
     * Names Flux owns rather than resolving from an icon set.
     *
     * `flux:icon.loading` is Flux's own spinner. Translating it would replace a
     * working animation with a static glyph.
     *
     * @var list<string>
     */
    public const array RESERVED = [
        'loading',
    ];

    /**
     * @var array<string, string>
     */
    public const array HEROICONS_TO_LUCIDE = [
        'arrow-path' => 'refresh-cw',
        'arrow-right-start-on-rectangle' => 'log-out',
        'bars-2' => 'menu',
        'check' => 'check',
        'chevron-down' => 'chevron-down',
        'chevron-left' => 'chevron-left',
        'chevron-right' => 'chevron-right',
        'chevron-up' => 'chevron-up',
        'chevron-up-down' => 'chevrons-up-down',
        'clipboard-document' => 'clipboard',
        'clipboard-document-check' => 'clipboard-check',
        'cog' => 'settings',
        'computer-desktop' => 'monitor',
        'document-duplicate' => 'copy',
        'envelope' => 'mail',
        'exclamation-triangle' => 'triangle-alert',
        'eye' => 'eye',
        'eye-slash' => 'eye-off',
        'finger-print' => 'fingerprint-pattern',
        'home' => 'house',
        'information-circle' => 'info',
        'key' => 'key-round',
        'lock-closed' => 'lock',
        'magnifying-glass' => 'search',
        'minus' => 'minus',
        'moon' => 'moon',
        'plus' => 'plus',
        'qr-code' => 'qr-code',
        'slash' => 'slash',
        'sun' => 'sun',
        'trash' => 'trash-2',
        'user-plus' => 'user-plus',
        'users' => 'users',
        'x-circle' => 'circle-x',
        'x-mark' => 'x',
    ];

    /**
     * The reverse direction only needs to cover the Lucide icons the kit vendors
     * in, since everything else is already a Heroicon.
     *
     * @var array<string, string>
     */
    public const array LUCIDE_TO_HEROICONS = [
        'book-open-text' => 'book-open',
        'chevrons-up-down' => 'chevron-up-down',
        'folder-git-2' => 'folder',
        'layout-grid' => 'squares-2x2',
    ];

    /**
     * Icons Flux renders from inside its own components.
     *
     * Refit cannot rewrite Flux's vendor code, so going all-Lucide means writing
     * an override at the *Heroicons* name for each of these — that is the only way
     * a `flux:select` chevron or a `viewable` input's eye follows the switch.
     *
     * Used as a fallback when the installed Flux package cannot be scanned.
     *
     * @var list<string>
     */
    public const array FLUX_INTERNAL_FALLBACK = [
        'check',
        'chevron-down',
        'chevron-left',
        'chevron-up-down',
        'clipboard-document',
        'clipboard-document-check',
        'exclamation-triangle',
        'eye',
        'eye-slash',
        'magnifying-glass',
        'minus',
        'slash',
        'x-mark',
    ];

    public static function toLucide(string $heroicon): ?string
    {
        return self::HEROICONS_TO_LUCIDE[$heroicon] ?? null;
    }

    public static function toHeroicons(string $lucide): ?string
    {
        return self::LUCIDE_TO_HEROICONS[$lucide] ?? null;
    }

    public static function isReserved(string $name): bool
    {
        return in_array($name, self::RESERVED, true);
    }

    /**
     * Names that are spelled the same in both sets need no rewrite, only an
     * override file.
     */
    public static function isSharedName(string $name): bool
    {
        return (self::HEROICONS_TO_LUCIDE[$name] ?? null) === $name;
    }
}
