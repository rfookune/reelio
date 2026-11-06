import { onMounted, ref } from 'vue';
import { formatShortDate } from '@/lib/dates';

/**
 * Formats a user's own dates in their time zone. The server renders them in UTC, so the first client render
 * matches the server-rendered page; once mounted, they switch to the browser's time zone, where "today" is the
 * user's today.
 */
export function useShortDate(): (timestamp: string) => string {
    const mounted = ref(false);

    onMounted(() => {
        mounted.value = true;
    });

    return (timestamp) =>
        formatShortDate(timestamp, mounted.value ? undefined : 'UTC');
}
