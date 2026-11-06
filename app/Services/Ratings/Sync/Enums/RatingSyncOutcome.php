<?php

namespace App\Services\Ratings\Sync\Enums;

enum RatingSyncOutcome
{
    case Synced;
    case Unchanged;
}
