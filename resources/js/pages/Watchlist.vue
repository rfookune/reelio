<script setup lang="ts">
import UserMovieList from '@/components/movie/UserMovieList.vue';
import type { UserMovieListPageProps } from '@/types';

defineProps<UserMovieListPageProps>();
</script>

<template>
    <UserMovieList v-bind="$props" collection="watchlist" />
</template>
