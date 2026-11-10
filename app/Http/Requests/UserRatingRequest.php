<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRatingRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['rating' => ['present', 'nullable', 'integer:strict', 'between:1,10']];
    }
}
