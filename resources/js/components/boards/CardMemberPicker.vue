<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import type { Card, User } from '@/types';
import { router } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';

const props = defineProps<{
    card: Card;
    boardMembers: User[];
}>();

function isAssigned(user: User): boolean {
    return (props.card.members ?? []).some((member) => member.id === user.id);
}

function toggle(user: User) {
    if (isAssigned(user)) {
        router.delete(route('card-members.destroy', [props.card.id, user.id]), { preserveScroll: true });
    } else {
        router.post(route('card-members.store', props.card.id), { user_id: user.id }, { preserveScroll: true });
    }
}
</script>

<template>
    <ul class="space-y-1">
        <li
            v-for="user in boardMembers"
            :key="user.id"
            class="flex cursor-pointer items-center justify-between gap-2 rounded-md p-2 text-sm hover:bg-accent"
            @click="toggle(user)"
        >
            <div class="flex items-center gap-2">
                <MemberAvatar :user="user" size="xs" />
                <p class="font-medium">{{ user.name }}</p>
            </div>
            <Check v-if="isAssigned(user)" class="size-4 text-primary" />
        </li>
    </ul>
</template>
