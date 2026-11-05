<?php

namespace App\Catalog;

enum CatalogStatus: string
{
    case Fresh = 'fresh';
    case Stale = 'stale';
    case Unavailable = 'unavailable';
}
