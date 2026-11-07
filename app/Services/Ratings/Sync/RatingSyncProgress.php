<?php

namespace App\Services\Ratings\Sync;

/**
 * Receives a dataset sync's progress as it runs, so a caller watching it can show how far it has got. An unchanged
 * file stops after the download; a failure stops wherever it happens.
 */
interface RatingSyncProgress
{
    /** The download has started. */
    public function downloading(): void;

    /** Rows read from the file so far, including those that will be skipped. Called again with the final total. */
    public function readRows(int $rowsRead): void;

    /** Every row has been read; the source's rows are being checked and replaced. */
    public function replacing(): void;
}
