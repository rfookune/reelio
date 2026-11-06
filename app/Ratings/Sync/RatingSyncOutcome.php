<?php

namespace App\Ratings\Sync;

enum RatingSyncOutcome
{
    case Synced;
    case Unchanged;
}
