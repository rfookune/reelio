<?php

namespace App\Ratings\Imdb;

enum ImdbImportOutcome
{
    case Imported;
    case Unchanged;
}
