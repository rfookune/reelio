import { init } from '@sentry/vue';
import type { App } from 'vue';
import type { SentryConfig } from '@/types';

export function initializeMonitoring(app: App, config: SentryConfig): void {
    if (!config.dsn) {
        return;
    }

    init({
        app,
        dsn: config.dsn,
        environment: config.environment,
        release: config.release ?? undefined,
    });
}
