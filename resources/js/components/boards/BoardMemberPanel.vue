<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import type { Board, User } from '@/types';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    board: Board;
    workspaceMembers: User[];
}>();

const open = defineModel<boolean>('open', { default: false });

const availableMembers = computed(() => {
    const memberIds = new Set((props.board.members ?? []).map((member) => member.id));

    return props.workspaceMembers.filter((user) => !memberIds.has(user.id));
});

function addMember(user: User) {
    router.post(route('board-members.store', props.board.id), { user_id: user.id }, { preserveScroll: true });
}

function removeMember(user: User) {
    router.delete(route('board-members.destroy', [props.board.id, user.id]), { preserveScroll: true });
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
                <SheetTitle>Board members</SheetTitle>
            </SheetHeader>

            <div class="mt-4 space-y-6">
                <div class="space-y-2">
                    <h3 class="text-sm font-medium text-muted-foreground">Members ({{ (board.members ?? []).length }})</h3>
                    <ul class="space-y-1">
                        <li
                            v-for="member in board.members"
                            :key="member.id"
                            class="flex items-center justify-between gap-2 rounded-md border p-2 text-sm"
                        >
                            <div class="flex items-center gap-2">
                                <MemberAvatar :user="member" size="xs" />
                                <p class="font-medium">
                                    {{ member.name }}
                                    <span v-if="member.id === board.user_id" class="text-xs text-muted-foreground">(creator)</span>
                                </p>
                            </div>
                            <Button v-if="member.id !== board.user_id" variant="ghost" size="sm" @click="removeMember(member)">Remove</Button>
                        </li>
                    </ul>
                </div>

                <div v-if="availableMembers.length" class="space-y-2">
                    <h3 class="text-sm font-medium text-muted-foreground">Add from workspace</h3>
                    <ul class="space-y-1">
                        <li
                            v-for="user in availableMembers"
                            :key="user.id"
                            class="flex items-center justify-between gap-2 rounded-md border p-2 text-sm"
                        >
                            <div class="flex items-center gap-2">
                                <MemberAvatar :user="user" size="xs" />
                                <p class="font-medium">{{ user.name }}</p>
                            </div>
                            <Button size="sm" @click="addMember(user)">Add</Button>
                        </li>
                    </ul>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
