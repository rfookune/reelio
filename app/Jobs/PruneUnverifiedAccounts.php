<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Keeps Reelio's published retention for unverified accounts, daily at 05:00 UTC: an account that never verified its
 * email is deleted 30 days after registration. An account holding a Watchlist or Watched fact is spared, because
 * actions need only a signed-in user. The account's sessions and events go with it, through their cascading foreign
 * keys. Repeating the job does no harm.
 */
class PruneUnverifiedAccounts implements ShouldQueue
{
    use Queueable;

    /** Days after registration at which an unverified account with no facts is deleted. */
    public const RETENTION_DAYS = 30;

    /** The next daily run is the retry. */
    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        User::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<=', now()->subDays(self::RETENTION_DAYS))
            ->whereNotExists(fn ($facts) => $facts->select(DB::raw(1))->from('user_movies')->whereColumn('user_movies.user_id', 'users.id'))
            ->delete();
    }
}
