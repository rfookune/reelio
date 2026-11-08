<?php

namespace App\Services\Catalog\Sources\Tmdb;

use App\Services\Catalog\Enums\OfferType;

class AppendedDataParser
{
    private const OFFER_TYPES = ['flatrate' => OfferType::Stream, 'free' => OfferType::Free, 'ads' => OfferType::Free, 'rent' => OfferType::Rent, 'buy' => OfferType::Buy];

    private const IMAGE_PATH_PATTERN = '/^\/[A-Za-z0-9_-]+\.(jpg|png|svg)$/D';

    private const MAXIMUM_DISPLAY_PRIORITY = 2_147_483_647;

    private const YOUTUBE_KEY_PATTERN = '/^[A-Za-z0-9_-]{11}$/D';

    public function safeImagePath(?string $path): ?string
    {
        return is_string($path) && preg_match(self::IMAGE_PATH_PATTERN, $path) === 1 ? $path : null;
    }

    /**
     * The whole billed cast, in billing order.
     *
     * @param  array<string, mixed>  $credits
     * @return list<array{id: int, name: string, character: ?string, profilePath: ?string}>
     */
    public function cast(array $credits): array
    {
        $members = array_filter((array) ($credits['cast'] ?? []), fn (mixed $member): bool => is_array($member) && is_int($member['id'] ?? null) && is_string($member['name'] ?? null));
        usort($members, fn (array $a, array $b): int => ($a['order'] ?? PHP_INT_MAX) <=> ($b['order'] ?? PHP_INT_MAX));

        return array_map(fn (array $member): array => [
            'id' => $member['id'],
            'name' => $member['name'],
            'character' => ($member['character'] ?? '') !== '' ? $member['character'] : null,
            'profilePath' => $member['profile_path'] ?? null,
        ], $members);
    }

    /**
     * Availability in the supported countries. TMDB's `flatrate` is a subscription stream; its `free` and `ads`
     * both become `free`, keeping one row per service with its best `display_priority`. TMDB's own `link` is
     * never used.
     *
     * @param  array<string, mixed>  $watchProviders
     * @return list<array{serviceId: int, serviceName: string, logoPath: ?string, country: string, offerType: string, displayPriority: int}>
     */
    public function availability(array $watchProviders): array
    {
        $offers = [];
        $results = (array) ($watchProviders['results'] ?? []);

        foreach (config('catalog.supported_countries') as $country) {
            if (! is_array($results[$country] ?? null)) {
                continue;
            }

            foreach (self::OFFER_TYPES as $tmdbType => $offerType) {
                foreach ((array) ($results[$country][$tmdbType] ?? []) as $entry) {
                    if (! is_array($entry) || ! is_int($entry['provider_id'] ?? null) || ! is_string($entry['provider_name'] ?? null) || $entry['provider_name'] === '') {
                        continue;
                    }

                    $priority = is_int($entry['display_priority'] ?? null) ? $entry['display_priority'] : PHP_INT_MAX;
                    $key = "{$country}:{$offerType->value}:{$entry['provider_id']}";

                    if (isset($offers[$key]) && $offers[$key]['displayPriority'] <= $priority) {
                        continue;
                    }

                    $logoPath = $entry['logo_path'] ?? null;

                    $offers[$key] = [
                        'serviceId' => $entry['provider_id'],
                        'serviceName' => $entry['provider_name'],
                        'logoPath' => is_string($logoPath) && preg_match(self::IMAGE_PATH_PATTERN, $logoPath) === 1 ? $logoPath : null,
                        'country' => $country,
                        'offerType' => $offerType->value,
                        'displayPriority' => max(0, min($priority, self::MAXIMUM_DISPLAY_PRIORITY)),
                    ];
                }
            }
        }

        return array_values($offers);
    }

    /**
     * Ranks YouTube Trailers before Teasers, then official, then a language match; ties go to the latest video
     * published on or before the primary release date, else the earliest.
     *
     * @param  array<string, mixed>  $videos
     * @return array{key: string, name: string, type: string}|null
     */
    public function trailer(array $videos, string $language, ?string $releaseDate): ?array
    {
        $languageCode = explode('-', $language)[0];
        $scored = [];

        foreach ((array) ($videos['results'] ?? []) as $video) {
            if (! is_array($video)
                || ($video['site'] ?? null) !== 'YouTube'
                || ! is_string($video['key'] ?? null)
                || preg_match(self::YOUTUBE_KEY_PATTERN, $video['key']) !== 1
                || ! in_array($video['type'] ?? null, ['Trailer', 'Teaser'], true)
                || ! is_string($video['name'] ?? null)) {
                continue;
            }

            $scored[] = [
                'score' => ($video['type'] === 'Trailer' ? 4 : 0) + (($video['official'] ?? false) === true ? 2 : 0) + (($video['iso_639_1'] ?? null) === $languageCode ? 1 : 0),
                'published' => is_string($video['published_at'] ?? null) ? $video['published_at'] : '',
                'video' => $video,
            ];
        }

        if ($scored === []) {
            return null;
        }

        $best = max(array_column($scored, 'score'));
        $tied = array_values(array_filter($scored, fn (array $candidate): bool => $candidate['score'] === $best));
        usort($tied, fn (array $a, array $b): int => strcmp($a['published'], $b['published']));

        $chosen = $tied[0];

        if ($releaseDate !== null) {
            foreach ($tied as $candidate) {
                if ($candidate['published'] !== '' && substr($candidate['published'], 0, 10) <= $releaseDate) {
                    $chosen = $candidate;
                }
            }
        }

        return ['key' => $chosen['video']['key'], 'name' => $chosen['video']['name'], 'type' => $chosen['video']['type']];
    }
}
