<script setup lang="ts">
import { useAnnouncer } from '@/composables/useAnnouncer';

const { message } = useAnnouncer();
</script>

<template>
    <div role="status" aria-live="polite" aria-atomic="true" class="sr-only">
        {{ message }}
    </div>
</template>
