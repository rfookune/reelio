export function isSafeTmdbPath(path: string | null): path is string {
    return (
        path !== null &&
        /^\/[A-Za-z0-9_-]+\.(jpg|png|svg)$/.test(path) &&
        !path.includes('\n')
    );
}

export function tmdbImageUrl(
    baseUrl: string,
    width: number,
    path: string,
): string {
    return `${baseUrl.replace(/\/$/, '')}/w${width}${path}`;
}

export function formatRuntime(minutes: number | null): string | null {
    if (minutes === null || minutes <= 0) {
        return null;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return hours > 0 ? `${hours}h${rest > 0 ? ` ${rest}m` : ''}` : `${rest}m`;
}

export function formatDate(date: string | null): string | null {
    if (date === null) {
        return null;
    }

    const parsed = new Date(`${date}T00:00:00Z`);

    return Number.isNaN(parsed.getTime())
        ? null
        : new Intl.DateTimeFormat('en-US', {
              dateStyle: 'long',
              timeZone: 'UTC',
          }).format(parsed);
}
