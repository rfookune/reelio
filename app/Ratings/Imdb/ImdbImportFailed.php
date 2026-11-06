<?php

namespace App\Ratings\Imdb;

use RuntimeException;

/**
 * The import stopped before the swap; the live table is untouched.
 */
class ImdbImportFailed extends RuntimeException {}
