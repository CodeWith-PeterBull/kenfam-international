<?php

/**
 * Builds LIKE patterns that treat operator input literally.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Support;

/**
 * SQLite has no default escape character and MySQL's is the backslash, so
 * every list search declares its own: patterns built here are used with
 * `column LIKE ? ESCAPE '!'` and never let `%` or `_` in a search term
 * widen the match.
 */
final class LikePattern
{
    public const ESCAPE = '!';

    /** The SQL fragment a search must append so the escape character applies. */
    public const CLAUSE = "LIKE ? ESCAPE '!'";

    /** Build a contains pattern with wildcards and the escape character neutralised, bounded to 100 characters. */
    public static function contains(string $term): string
    {
        return '%'.str_replace([self::ESCAPE, '%', '_'], [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'], mb_substr(trim($term), 0, 100)).'%';
    }

    /** Prevent instantiation of this stateless utility. */
    private function __construct() {}
}
