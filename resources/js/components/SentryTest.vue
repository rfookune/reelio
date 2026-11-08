<script>
import * as Sentry from '@sentry/vue';

export default {
    methods: {
        triggerError() {
            // Send a test metric before throwing the error
            Sentry.metrics.count('test_counter', 1);
            throw new Error('Sentry Test Error');
        },
    },
};
</script>

<template>
    <button type="button" @click="triggerError">Break the world</button>
</template>
