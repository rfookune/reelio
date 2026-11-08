<?php

namespace App\Models;

use Database\Factories\GenreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['id', 'name'])]
class Genre extends Model
{
    /** @use HasFactory<GenreFactory> */
    use HasFactory;

    public $incrementing = false;

    /** @return BelongsToMany<Title, $this> */
    public function titles(): BelongsToMany
    {
        return $this->belongsToMany(Title::class);
    }

    /** @return BelongsToMany<Movie, $this> */
    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class, 'genre_title', 'genre_id', 'title_id');
    }
}
