<?php

namespace App\Http\Requests;

use App\Enums\Surface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Watchlist or Watched action, naming the surface it was used on for analytics.
 */
class UserMovieActionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', Rule::enum(Surface::class)->only(Surface::movieActionSources())],
        ];
    }

    public function source(): Surface
    {
        return Surface::from($this->validated('source'));
    }
}
