import { computed, onMounted, ref } from 'vue';
import { formatShortDate } from '@/lib/dates';

/**
 * Formats a user's own dates in their time zone. The server renders them in UTC, so the first client render
 * matches the server-rendered page; once mounted, they switch to the browser's time zone, where "today" is the
 * user's today. Localized dates also switch to the browser's locale after mounting.
 */
export function useShortDate({
    localized = false,
}: { localized?: boolean } = {}): (timestamp: string) => string {
    const mounted = ref(false);

    onMounted(() => {
        mounted.value = true;
    });

    const formatter = computed(
        () =>
            new Intl.DateTimeFormat(mounted.value ? undefined : 'en-US', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                timeZone: mounted.value ? undefined : 'UTC',
            }),
    );

    return (timestamp) =>
        localized
            ? formatter.value.format(new Date(timestamp))
            : formatShortDate(timestamp, mounted.value ? undefined : 'UTC');
}
