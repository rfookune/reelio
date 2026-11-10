<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRatingRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['rating' => ['present', Rule::requiredIf($this->input('rating') !== null), 'nullable', 'integer:strict', 'between:1,10']];
    }
}
