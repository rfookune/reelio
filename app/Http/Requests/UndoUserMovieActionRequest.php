<?php

namespace App\Http\Requests;

use App\Enums\UserTitleAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Undo of the Watchlist or Watched action a toast offered, named so a toast for an older action can't undo a later
 * one. It carries no dates: those come from the server's record of the action.
 */
class UndoUserMovieActionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::enum(UserTitleAction::class)],
        ];
    }

    public function action(): UserTitleAction
    {
        return UserTitleAction::from($this->validated('action'));
    }
}
