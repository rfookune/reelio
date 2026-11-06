<?php

namespace App\Ratings\Exceptions;

use RuntimeException;

/**
 * A full replacement of a source's rows was refused or failed; the live rows are untouched.
 */
class RatingReplacementRefused extends RuntimeException {}
