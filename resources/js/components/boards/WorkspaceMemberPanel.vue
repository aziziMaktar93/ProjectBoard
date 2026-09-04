<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { showToast } from '@/composables/useToast';
import type { SharedData, User, Workspace } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    workspace: Workspace;
    members: User[];
    isOwner: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const currentUserId = usePage<SharedData>().props.auth.user.id;

const query = ref('');
const results = ref<User[]>([]);
const searching = ref(false);
let searchToken = 0;

watch(query, async (value) => {
    const trimmed = value.trim();

    if (!trimmed) {
        results.value = [];
        return;
    }

    const token = ++searchToken;
    searching.value = true;

    try {
        const response = await fetch(`${route('workspace-members.search', props.workspace.id)}?q=${encodeURIComponent(trimmed)}`, {
            headers: { Accept: 'application/json' },
        });
        const data = (await response.json()) as { users: User[] };

        if (token === searchToken) {
            results.value = data.users;
        }
    } catch {
        if (token === searchToken) {
            results.value = [];
        }
    } finally {
        if (token === searchToken) {
            searching.value = false;
        }
    }
});

function addMember(user: User) {
    router.post(
        route('workspace-members.store', props.workspace.id),
        { user_id: user.id },
        {
            preserveScroll: true,
            onSuccess: () => {
                query.value = '';
                results.value = [];
                showToast('Member added');
            },
            onError: () => showToast('Could not add member, try again.', 'error'),
        },
    );
}

function removeMember(user: User) {
    const label = user.id === currentUserId ? 'leave this workspace' : `remove ${user.name} from this workspace`;

    if (!confirm(`Are you sure you want to ${label}?`)) {
        return;
    }

    router.delete(route('workspace-members.destroy', [props.workspace.id, user.id]), {
        preserveScroll: true,
        onSuccess: () => showToast('Member removed'),
        onError: () => showToast('Could not remove member, try again.', 'error'),
    });
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
                <SheetTitle>Workspace members</SheetTitle>
            </SheetHeader>

            <div class="mt-4 space-y-6">
                <div v-if="isOwner" class="space-y-2">
                    <Input v-model="query" placeholder="Search by name or email" />
                    <ul v-if="results.length" class="space-y-1 rounded-md border p-1">
                        <li
                            v-for="user in results"
                            :key="user.id"
                            class="flex items-center justify-between gap-2 rounded p-2 text-sm hover:bg-accent"
                        >
                            <div class="flex items-center gap-2">
                                <MemberAvatar :user="user" size="xs" />
                                <div>
                                    <p class="font-medium">{{ user.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ user.email }}</p>
                                </div>
                            </div>
                            <Button size="sm" @click="addMember(user)">Add</Button>
                        </li>
                    </ul>
                    <p v-else-if="query.trim() && !searching" class="text-sm text-muted-foreground">No users found.</p>
                </div>

                <div class="space-y-2">
                    <h3 class="text-sm font-medium text-muted-foreground">Members ({{ members.length }})</h3>
                    <ul class="space-y-1">
                        <li
                            v-for="member in members"
                            :key="member.id"
                            class="flex items-center justify-between gap-2 rounded-md border p-2 text-sm"
                        >
                            <div class="flex items-center gap-2">
                                <MemberAvatar :user="member" size="xs" />
                                <p class="font-medium">
                                    {{ member.name }}
                                    <span v-if="member.id === workspace.owner_id" class="text-xs text-muted-foreground">(owner)</span>
                                </p>
                            </div>
                            <Button
                                v-if="member.id !== workspace.owner_id && (isOwner || member.id === currentUserId)"
                                variant="ghost"
                                size="sm"
                                @click="removeMember(member)"
                            >
                                {{ member.id === currentUserId ? 'Leave' : 'Remove' }}
                            </Button>
                        </li>
                    </ul>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
