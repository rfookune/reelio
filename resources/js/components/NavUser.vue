<script setup lang="ts">
import type { User } from '@/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';

defineProps<{
    user: User;
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="flex size-9 items-center justify-center rounded-full"
                aria-label="Profile"
                data-test="user-menu-button"
            >
                <Avatar class="size-9 ring-1 ring-line">
                    <AvatarImage v-if="user.avatar" :src="user.avatar" alt="" />
                    <AvatarFallback class="text-sm font-semibold">
                        {{ getInitials(user.name) }}
                    </AvatarFallback>
                </Avatar>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" :side-offset="8" class="w-56">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
