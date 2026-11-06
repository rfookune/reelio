<?php

namespace App\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Locale;

/**
 * The country whose Availability a visitor sees, worked out afresh on every request: the signed-in user's
 * `preferred_country`, or a guest's explicit choice from the country cookie, then the country the edge detected
 * from the connection, then the default. Once signed in, the account is the only place a choice lives (the
 * guest's is copied there on sign-in), so a user who clears their preference gets detection, not a stale cookie.
 * Only supported countries are ever used. Detection is never stored; only an explicit choice is.
 */
readonly class VisitorCountry
{
    /**
     * The strictly necessary first-party cookie that holds a guest's explicit choice.
     */
    public const COOKIE = 'country';

    /**
     * How long a guest's choice is remembered, in minutes (a year).
     */
    public const COOKIE_MINUTES = 525600;

    /**
     * What Cloudflare sends when it has no country information. Its Tor value, `T1`, fails the two-letter check.
     */
    private const NO_COUNTRY = 'XX';

    /**
     * @param  bool  $detectedUnsupported  The edge detected a country Reelio doesn't support, so the default is shown instead.
     */
    private function __construct(public string $code, public bool $detectedUnsupported = false) {}

    public static function resolve(Request $request): self
    {
        $user = $request->user();
        $choice = $user instanceof User ? $user->preferred_country : self::guestChoice($request);

        if (self::isSupported($choice)) {
            return new self($choice);
        }

        $detected = self::detected($request);
        $default = config('catalog.default_country');

        if ($detected === null) {
            return new self($default);
        }

        return self::isSupported($detected) ? new self($detected) : new self($default, detectedUnsupported: true);
    }

    /**
     * The guest's explicit choice, ignored unless it names a supported country.
     */
    public static function guestChoice(Request $request): ?string
    {
        $choice = $request->cookie(self::COOKIE);

        return self::isSupported($choice) ? $choice : null;
    }

    /**
     * @phpstan-assert-if-true =string $country
     */
    public static function isSupported(mixed $country): bool
    {
        return is_string($country) && in_array($country, config('catalog.supported_countries'), true);
    }

    public static function name(string $code): string
    {
        return Locale::getDisplayRegion("-{$code}", 'en') ?: $code;
    }

    /**
     * The supported countries a visitor can choose from, alphabetically by name.
     *
     * @return list<array{code: string, name: string}>
     */
    public static function choices(): array
    {
        $choices = array_map(fn (string $code): array => ['code' => $code, 'name' => self::name($code)], config('catalog.supported_countries'));
        usort($choices, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $choices;
    }

    /**
     * The country the edge detected from the connection, if it sent one.
     */
    private static function detected(Request $request): ?string
    {
        $header = $request->header(config('catalog.country_header'));

        if (! is_string($header)) {
            return null;
        }

        $country = strtoupper(trim($header));

        return preg_match('/^[A-Z]{2}$/', $country) === 1 && $country !== self::NO_COUNTRY ? $country : null;
    }
}
