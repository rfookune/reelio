<?php

namespace App\Http\Requests;

use App\Http\Support\PickerCriteria;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A Movie picker pick: optional any-match genres and, for Pick again, the previous pick's catalog id.
 */
class MoviePickerRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'genres' => ['nullable', 'array', 'max:50'],
            'genres.*' => ['integer', 'min:1', 'distinct'],
            'previous' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function criteria(): PickerCriteria
    {
        $previous = $this->validated('previous');

        return new PickerCriteria(
            array_values(array_map(intval(...), $this->validated('genres') ?? [])),
            $previous === null ? null : (int) $previous,
        );
    }
}
