<?php

namespace App\Ratings\Sync;

use RuntimeException;

/**
 * A dataset sync stopped before the swap; the source's live rows are untouched.
 */
class RatingSyncFailed extends RuntimeException {}
