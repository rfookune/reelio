<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TvShowWatchlistRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        abort_unless(config('catalog.tv_shows.enabled'), 404);

        return ['source' => ['required', 'string', Rule::in(['tv-show', 'search', 'watchlist', 'watched'])]];
    }

    public function source(): string
    {
        return $this->validated('source');
    }
}
