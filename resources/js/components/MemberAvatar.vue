<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

withDefaults(
    defineProps<{
        user: User;
        size?: 'xs' | 'sm';
    }>(),
    {
        size: 'sm',
    },
);

const { getInitials } = useInitials();
</script>

<template>
    <Avatar :class="size === 'xs' ? 'size-6 text-[10px]' : 'size-8 text-xs'" :title="user.name">
        <AvatarFallback class="bg-primary/10 text-primary">{{ getInitials(user.name) }}</AvatarFallback>
    </Avatar>
</template>
