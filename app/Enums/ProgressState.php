<?php

namespace App\Enums;

/**
 * Where a user stands in a TV Show's Watched progress; the value is the label shown in the UI and sent over the wire.
 */
enum ProgressState: string
{
    case NotStarted = 'Not started';
    case InProgress = 'In progress';
    case Completed = 'Completed';
}
