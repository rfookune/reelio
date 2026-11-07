<?php

namespace App\Console\Commands;

use App\Services\Catalog\Catalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * For when the licence to TMDB's data ends, run by hand: see `Catalog::purgeSourceData()`. Every Movie keeps its row
 * and users' facts but loses what it holds from TMDB, and every TMDB rating is deleted. IMDb ratings aren't TMDB's,
 * so they stay. Refreshes after it fetch everything again unless the TMDB token has been removed.
 */
#[Signature('catalog:purge {--force : Purge without asking first}')]
#[Description('Clear the data stored from TMDB, keeping users\' facts')]
class PurgeCatalogCommand extends Command
{
    public function handle(Catalog $catalog): int
    {
        if (! $this->option('force') && ! $this->confirm('Clear the TMDB data Reelio stores? Users\' facts are kept. Remove the TMDB token first, or the next refreshes fetch it all again.')) {
            $this->components->warn('Nothing was purged.');

            return self::FAILURE;
        }

        $purged = $catalog->purgeSourceData();
        $this->components->info("Cleared {$purged['movies']} Movies and deleted {$purged['ratings']} TMDB ratings.");

        return self::SUCCESS;
    }
}
