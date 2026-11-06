<?php

namespace App\Services\Catalog\Enums;

enum CatalogStatus: string
{
    case Fresh = 'fresh';
    case Stale = 'stale';
    case Unavailable = 'unavailable';
}
