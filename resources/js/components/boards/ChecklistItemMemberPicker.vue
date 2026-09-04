<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import { showToast } from '@/composables/useToast';
import type { ChecklistItem, User } from '@/types';
import { router } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';

const props = defineProps<{
    item: ChecklistItem;
    boardMembers: User[];
}>();

function isAssigned(user: User): boolean {
    return (props.item.members ?? []).some((member) => member.id === user.id);
}

function toggle(user: User) {
    if (isAssigned(user)) {
        router.delete(route('checklist-item-members.destroy', [props.item.id, user.id]), {
            preserveScroll: true,
            onSuccess: () => showToast('Member removed'),
            onError: () => showToast('Could not remove member, try again.', 'error'),
        });
    } else {
        router.post(
            route('checklist-item-members.store', props.item.id),
            { user_id: user.id },
            {
                preserveScroll: true,
                onSuccess: () => showToast('Member added'),
                onError: () => showToast('Could not add member, try again.', 'error'),
            },
        );
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
