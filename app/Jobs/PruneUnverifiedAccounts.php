<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Keeps Reelio's published retention for unverified accounts, daily at 05:00 UTC: an account that never verified its
 * email is deleted 30 days after registration. An account holding a Watchlist or Watched fact is spared, because
 * actions need only a signed-in user. Its sessions are deleted with it, and its events go through their cascading
 * foreign key. Repeating the job does no harm.
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
        DB::transaction(function (): void {
            $accounts = User::query()
                ->whereNull('email_verified_at')
                ->where('created_at', '<=', now()->subDays(self::RETENTION_DAYS))
                ->whereNotExists(fn ($facts) => $facts->select(DB::raw(1))->from('user_titles')->whereColumn('user_titles.user_id', 'users.id'));

            DB::table(config('session.table'))->whereIn('user_id', (clone $accounts)->select('users.id'))->delete();
            $accounts->delete();
        });
    }
}
