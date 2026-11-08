<?php

namespace App\Enums;

/** The event names recorded by analytics and consumed by metrics and retention jobs. */
enum AnalyticsEvent: string
{
    case AccountRegistered = 'account_registered';
    case MovieViewed = 'movie_viewed';
    case MovieWatchlisted = 'movie_watchlisted';
    case MovieUnwatchlisted = 'movie_unwatchlisted';
    case MovieMarkedWatched = 'movie_marked_watched';
    case MovieUnmarkedWatched = 'movie_unmarked_watched';
    case UserMovieActionUndone = 'user_movie_action_undone';
    case MoviePickerUsed = 'movie_picker_used';
    case TitleSearched = 'title_searched';
    case AvailabilityLinkClicked = 'availability_link_clicked';
    case TvShowViewed = 'tv_show_viewed';
    case TvShowWatchlisted = 'tv_show_watchlisted';
    case TvShowUnwatchlisted = 'tv_show_unwatchlisted';
    case EpisodeMarkedWatched = 'episode_marked_watched';
    case EpisodeUnmarkedWatched = 'episode_unmarked_watched';
    case SeasonMarkedWatched = 'season_marked_watched';
    case SeasonUnmarkedWatched = 'season_unmarked_watched';
    case TvShowMarkedWatched = 'tv_show_marked_watched';
    case TvShowUnmarkedWatched = 'tv_show_unmarked_watched';
    case TvShowCompleted = 'tv_show_completed';
    case TvShowActionUndone = 'tv_show_action_undone';
}
