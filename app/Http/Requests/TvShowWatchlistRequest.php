<?php

namespace App\Http\Requests;

use App\Enums\Surface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TvShowWatchlistRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        abort_unless(config('catalog.tv_shows.enabled'), 404);

        return ['source' => ['required', 'string', Rule::enum(Surface::class)->only(Surface::tvShowActionSources())]];
    }

    public function source(): Surface
    {
        return Surface::from($this->validated('source'));
    }
}
