<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { formatTimestamp, sentenceFor } from '@/lib/activitySentence';
import type { Card, SharedData } from '@/types';
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
