<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import { tileGradient } from '@/lib/colorGradient';
import { colorForUser } from '@/lib/memberColor';
import type { User } from '@/types';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        user: User;
        size?: 'xs' | 'sm';
    }>(),
    {
        size: 'sm',
    },
);

const { getInitials } = useInitials();

const gradient = computed(() => tileGradient(colorForUser(props.user.id)));
</script>

<template>
    <Avatar
        :class="size === 'xs' ? 'size-6 text-[10px]' : 'size-8 text-xs'"
        class="ring-2 ring-white dark:ring-neutral-900"
        :title="user.name"
    >
        <AvatarFallback
            class="flex h-full w-full items-center justify-center font-semibold text-white"
            :style="{ backgroundImage: gradient }"
        >
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>
</template>
