<?php

declare(strict_types=1);

namespace App\Support;

use Transliterator;

/**
 * Outils de chaînes.
 */
final class Str
{
    /** Slug d'URL : « Riviera Golf (Cocody) » → « riviera-golf-cocody », « M'Pouto » → « mpouto ». */
    public static function slug(string $value, int $maxLength = 120): string
    {
        static $transliterator = null;
        $transliterator ??= Transliterator::create('Any-Latin; Latin-ASCII; Lower()');

        $ascii = $transliterator !== null ? (string) $transliterator->transliterate($value) : strtolower($value);
        $ascii = str_replace(["'", '’'], '', $ascii);
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');

        return rtrim(substr($slug, 0, $maxLength), '-');
    }

    /** Code technique : « Titre de propriété » → « titre_de_propriete ». */
    public static function code(string $value, int $maxLength = 60): string
    {
        return str_replace('-', '_', self::slug($value, $maxLength));
    }
}
