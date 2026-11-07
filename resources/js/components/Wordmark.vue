<script setup lang="ts">
import logoOnDark from '../../images/reelio-logo-dark.svg';
import logoOnLight from '../../images/reelio-logo-light.svg';

/**
 * Reelio's wordmark, in the variant drawn for the current theme. Both are in the page and the theme's `.dark` class
 * shows one, so the right one paints with the first render, server-rendered or not.
 */
</script>

<template>
    <span class="inline-flex items-center">
        <img
            :src="logoOnLight"
            alt="Reelio"
            width="79"
            height="32"
            class="block h-8 w-auto dark:hidden"
        />
        <img
            :src="logoOnDark"
            alt="Reelio"
            width="79"
            height="32"
            class="hidden h-8 w-auto dark:block"
        />
    </span>
</template>
