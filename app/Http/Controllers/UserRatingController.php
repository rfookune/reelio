<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRatingRequest;
use App\Models\Title;
use App\Models\UserTitle;
use Illuminate\Http\Response;

class UserRatingController extends Controller
{
    public function update(UserRatingRequest $request, Title $title): Response
    {
        UserTitle::change($request->user(), $title, function (UserTitle $row) use ($request): void {
            $row->rating = $request->validated('rating');
        });

        return response()->noContent();
    }
}
