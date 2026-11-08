<?php

namespace App\Services\Catalog;

use App\Services\Catalog\Sources\TvShowDetails;

/** The appended page data, cached separately from the stored Season and Episode inventory. */
readonly class TvShowPageData
{
    /**
     * @param  list<string>  $creators
     * @param  list<string>  $networks
     * @param  list<array{id: int, name: string, character: ?string, profilePath: ?string}>  $cast
     * @param  array{key: string, name: string, type: string}|null  $trailer
     */
    public function __construct(
        public array $creators = [],
        public array $networks = [],
        public array $cast = [],
        public ?string $certification = null,
        public ?array $trailer = null,
    ) {}

    public static function fromDetails(TvShowDetails $details): self
    {
        return new self($details->creators, $details->networks, $details->cast, $details->certification, $details->trailer);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        try {
            return new self(...$data);
        } catch (\Error) {
            return null;
        }
    }
}
