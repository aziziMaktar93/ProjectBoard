<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import type { Card, CardActivity, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    card: Card;
}>();

const currentUser = usePage<SharedData>().props.auth.user;

const commentBody = ref('');
const submitting = ref(false);

function submitComment() {
    const body = commentBody.value.trim();

    if (!body || submitting.value) {
        return;
    }

    submitting.value = true;

    router.post(
        route('card-activities.store', props.card.id),
        { body },
        {
            preserveScroll: true,
            onSuccess: () => {
                commentBody.value = '';
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

const activities = computed(() => props.card.activities ?? []);

function sentenceFor(activity: CardActivity): string {
    const data = activity.data ?? {};

    switch (activity.type) {
        case 'moved':
            return `moved this card from ${data.from_list} to ${data.to_list}`;
        case 'checklist_item_completed':
            return `completed ${data.item_name} on this card`;
        case 'checklist_item_uncompleted':
            return `marked ${data.item_name} incomplete on this card`;
        case 'member_added':
            return `added ${data.member_name} to this card`;
        case 'member_removed':
            return `removed ${data.member_name} from this card`;
        case 'archived':
            return 'archived this card';
        case 'restored':
            return 'restored this card';
        default:
            return '';
    }
}

function formatTimestamp(value: string): string {
    return new Date(value).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-start gap-2">
            <MemberAvatar :user="currentUser" size="sm" />
            <div class="flex-1 space-y-2">
                <textarea
                    v-model="commentBody"
                    rows="2"
                    placeholder="Write a comment..."
                    class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    @keydown.enter.meta.exact.prevent="submitComment"
                    @keydown.enter.ctrl.exact.prevent="submitComment"
                />
                <Button v-if="commentBody.trim()" size="sm" :disabled="submitting" @click="submitComment">Comment</Button>
            </div>
        </div>

        <ul class="space-y-3">
            <li v-for="activity in activities" :key="activity.id" class="flex items-start gap-2">
                <MemberAvatar :user="activity.user" size="sm" />
                <div class="min-w-0 flex-1">
                    <template v-if="activity.type === 'comment'">
                        <div class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
                            <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ activity.user.name }}</p>
                            <p class="whitespace-pre-wrap text-neutral-700 dark:text-neutral-300">{{ activity.body }}</p>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">{{ formatTimestamp(activity.created_at) }}</p>
                    </template>
                    <template v-else>
                        <p class="text-sm text-neutral-700 dark:text-neutral-300">
                            <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ activity.user.name }}</span>
                            {{ sentenceFor(activity) }}
                        </p>
                        <p class="text-xs text-muted-foreground">{{ formatTimestamp(activity.created_at) }}</p>
                    </template>
                </div>
            </li>
        </ul>
    </div>
</template>
