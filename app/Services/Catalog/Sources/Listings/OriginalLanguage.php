<?php

namespace App\Services\Catalog\Sources\Listings;

/**
 * The languages Browse offers to narrow by a Title's original language, a short curated list; the value is the
 * source's code for the language a Title was made in. It says nothing about dubbing or subtitles.
 */
enum OriginalLanguage: string
{
    case English = 'en';
    case Spanish = 'es';
    case French = 'fr';
    case German = 'de';
    case Italian = 'it';
    case Japanese = 'ja';
    case Korean = 'ko';
    case Hindi = 'hi';
    case Mandarin = 'zh';
    case Portuguese = 'pt';
    case Turkish = 'tr';
    case Swedish = 'sv';

    /** The language's name in that language, which is how Browse shows it. */
    public function nativeName(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Spanish => 'Español',
            self::French => 'Français',
            self::German => 'Deutsch',
            self::Italian => 'Italiano',
            self::Japanese => '日本語',
            self::Korean => '한국어',
            self::Hindi => 'हिन्दी',
            self::Mandarin => '中文',
            self::Portuguese => 'Português',
            self::Turkish => 'Türkçe',
            self::Swedish => 'Svenska',
        };
    }

    /**
     * The curated list as Browse offers it, in the order above.
     *
     * @return list<array{code: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $language): array => ['code' => $language->value, 'name' => $language->nativeName()], self::cases());
    }
}
