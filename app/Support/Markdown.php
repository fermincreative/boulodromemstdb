<?php

namespace App\Support;

/**
 * Text typed by members (names) is inserted into e-mails that are rendered as Markdown. Escaping the characters
 * that make links, images, emphasis or HTML keeps "[Betaal hier](https://…)" in a name from becoming a link.
 * Hyphens, dots and apostrophes are left alone, so normal names never get visible backslashes.
 */
class Markdown
{
    private const SPECIAL = ['\\', '`', '*', '_', '[', ']', '(', ')', '<', '>', '!', '#', '|', '~', '{', '}'];

    public static function escape(?string $text): string
    {
        return strtr((string) $text, array_combine(self::SPECIAL, array_map(fn (string $char) => '\\'.$char, self::SPECIAL)));
    }
}
