<?php

namespace App\Services\Catalog\Enums;

/**
 * The crew jobs kept for a Title's Full Cast page. The case order is the order the jobs are listed in.
 */
enum CrewJob: string
{
    case Director = 'Director';
    case Writer = 'Writer';
    case Screenplay = 'Screenplay';
    case Story = 'Story';
    case Novel = 'Novel';
    case Producer = 'Producer';
    case ExecutiveProducer = 'Executive Producer';

    /** Where the job is listed among the others, first being 0. */
    public function position(): int
    {
        return array_flip(self::names())[$this->value];
    }

    /**
     * Every kept job's TMDB name, in listing order.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'value');
    }
}
