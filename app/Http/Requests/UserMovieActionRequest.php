<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Watchlist or Watched action, naming the surface it was used on for analytics.
 */
class UserMovieActionRequest extends FormRequest
{
    /** The surfaces that carry Watchlist and Watched controls. */
    public const SOURCES = ['discover', 'search', 'movie', 'watchlist', 'watched', 'picker'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', Rule::in(self::SOURCES)],
        ];
    }

    public function source(): string
    {
        return $this->validated('source');
    }
}
