<?php

namespace App\Http\Support;

use App\Models\Availability;
use App\Models\Title;
use App\Services\Catalog\Enums\OfferType;

/**
 * Where a Title streams for one country, from stored Availability: the non-empty offer-type groups in tab order,
 * each listing its Streaming services in `display_priority` order, and the freshness line from `synced_at`.
 */
class WhereToWatch
{
    private const OUT_OF_DATE_DAYS = 7;

    /**
     * The block is flagged as possibly out of date past seven calendar days since the last sync, or when the
     * caller's own refresh of the Title failed.
     *
     * @param  list<OfferType>|null  $offerTypes  The groups to include; every offer type when null.
     * @return array{country: string, countryName: string, countryUnsupported: bool, countries: list<array{code: string, name: string}>, groups: list<array{type: string, label: string, caption: ?string, services: list<array{id: int, name: string, logoPath: ?string}>}>, updated: ?string, outOfDate: bool}
     */
    public static function for(Title $title, VisitorCountry $visitorCountry, bool $refreshFailed = false, ?array $offerTypes = null): array
    {
        $country = $visitorCountry->code;
        $offerTypes ??= OfferType::cases();
        $offers = $title->availabilities()
            ->with('streamingService')
            ->where('country', $country)
            ->whereIn('offer_type', array_map(fn (OfferType $offerType): string => $offerType->value, $offerTypes))
            ->orderBy('display_priority')
            ->orderBy('streaming_service_id')
            ->get()
            ->groupBy(fn (Availability $availability): string => $availability->offer_type->value);
        $groups = [];

        foreach (OfferType::cases() as $offerType) {
            if (! $offers->has($offerType->value)) {
                continue;
            }

            $groups[] = [
                'type' => $offerType->value,
                'label' => $offerType->label(),
                'caption' => $offerType->caption(),
                'services' => array_values($offers[$offerType->value]->map(fn (Availability $availability): array => [
                    'id' => $availability->streamingService->id,
                    'name' => $availability->streamingService->name,
                    'logoPath' => $availability->streamingService->logo_path,
                ])->all()),
            ];
        }

        $daysSinceSync = $title->synced_at === null ? null : (int) $title->synced_at->startOfDay()->diffInDays(now()->startOfDay());

        return [
            'country' => $country,
            'countryName' => VisitorCountry::name($country),
            'countryUnsupported' => $visitorCountry->detectedUnsupported,
            'countries' => VisitorCountry::choices(),
            'groups' => $groups,
            'updated' => self::updatedLabel($daysSinceSync),
            'outOfDate' => $refreshFailed || ($daysSinceSync !== null && $daysSinceSync > self::OUT_OF_DATE_DAYS),
        ];
    }

    private static function updatedLabel(?int $days): ?string
    {
        if ($days === null) {
            return null;
        }

        return match (true) {
            $days <= 0 => 'Updated today',
            $days === 1 => 'Updated yesterday',
            default => "Updated {$days} days ago",
        };
    }
}
