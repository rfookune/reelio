<?php

namespace App\Ratings\Sync\Enums;

enum RatingSyncOutcome
{
    case Synced;
    case Unchanged;
}
