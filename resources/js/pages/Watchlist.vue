<script setup lang="ts">
import UserList from '@/components/movie/UserList.vue';
import type { UserListPageProps } from '@/types';

defineProps<UserListPageProps>();
</script>

<template>
    <UserList v-bind="$props" collection="watchlist" />
</template>
