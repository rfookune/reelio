<?php

namespace App\Console\Support;

use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\Sync\RatingSyncProgress;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Shows a dataset sync's progress in the console: a line for the download, a running count of rows read with the
 * time elapsed (the file's row total isn't known until it has been read), then a line for the replacement.
 */
class RatingSyncProgressBar implements RatingSyncProgress
{
    private ?ProgressBar $bar = null;

    public function __construct(private OutputStyle $output, private RatingSourceName $source) {}

    public function downloading(): void
    {
        $this->output->writeln("  Downloading the {$this->source->label()} dataset…");
    }

    public function readRows(int $rowsRead): void
    {
        if ($this->bar === null) {
            $this->bar = $this->output->createProgressBar();
            $this->bar->setFormat('  %message% [%elapsed:6s%]');
        }

        $this->bar->setMessage(number_format($rowsRead).' rows read');
        $this->bar->setProgress($rowsRead);
    }

    public function replacing(): void
    {
        $this->finish();
        $this->output->writeln("  Replacing the live {$this->source->label()} rows…");
    }

    /** Draws the bar's last state and ends its line, so the next message starts on a line of its own. */
    public function finish(): void
    {
        if ($this->bar === null) {
            return;
        }

        $this->bar->finish();
        $this->output->newLine();
        $this->bar = null;
    }
}
