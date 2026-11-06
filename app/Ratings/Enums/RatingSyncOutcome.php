<?php

namespace App\Ratings\Enums;

enum RatingSyncOutcome
{
    case Synced;
    case Unchanged;
}
