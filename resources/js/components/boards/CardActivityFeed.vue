<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { formatTimestamp, sentenceFor } from '@/lib/activitySentence';
import type { Card, SharedData, User } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps<{
    card: Card;
    boardMembers: User[];
}>();

const currentUser = usePage<SharedData>().props.auth.user;

const commentBody = ref('');
const submitting = ref(false);
const textareaRef = ref<HTMLTextAreaElement | null>(null);

const mentionQuery = ref<string | null>(null);
const mentionStart = ref(0);
const activeSuggestionIndex = ref(0);

const mentionSuggestions = computed(() => {
    if (mentionQuery.value === null) {
        return [];
    }

    const query = mentionQuery.value.toLowerCase();

    return props.boardMembers.filter((member) => member.name.toLowerCase().includes(query)).slice(0, 6);
});

function onCommentInput() {
    const el = textareaRef.value;

    if (!el) {
        return;
    }

    const cursor = el.selectionStart ?? commentBody.value.length;
    const textBeforeCursor = commentBody.value.slice(0, cursor);
    const match = textBeforeCursor.match(/(?:^|\s)@([^\s@]*)$/);

    if (match) {
        mentionQuery.value = match[1];
        mentionStart.value = cursor - match[1].length - 1;
        activeSuggestionIndex.value = 0;
    } else {
        mentionQuery.value = null;
    }
}

function selectMention(member: User) {
    const el = textareaRef.value;

    if (!el || mentionQuery.value === null) {
        return;
    }

    const cursor = el.selectionStart ?? commentBody.value.length;
    const before = commentBody.value.slice(0, mentionStart.value);
    const after = commentBody.value.slice(cursor);
    commentBody.value = `${before}@${member.name} ${after}`;
    mentionQuery.value = null;

    nextTick(() => {
        const newCursor = before.length + member.name.length + 2;
        el.focus();
        el.setSelectionRange(newCursor, newCursor);
    });
}

function onCommentKeydown(event: KeyboardEvent) {
    if (mentionSuggestions.value.length > 0) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            activeSuggestionIndex.value = (activeSuggestionIndex.value + 1) % mentionSuggestions.value.length;
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            activeSuggestionIndex.value = (activeSuggestionIndex.value - 1 + mentionSuggestions.value.length) % mentionSuggestions.value.length;
            return;
        }

        if (event.key === 'Enter' || event.key === 'Tab') {
            event.preventDefault();
            selectMention(mentionSuggestions.value[activeSuggestionIndex.value]);
            return;
        }

        if (event.key === 'Escape') {
            mentionQuery.value = null;
            return;
        }
    }

    if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        event.preventDefault();
        submitComment();
    }
}

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
            <div class="relative flex-1 space-y-2">
                <textarea
                    ref="textareaRef"
                    v-model="commentBody"
                    rows="2"
                    placeholder="Write a comment... (type @ to mention someone)"
                    class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    @input="onCommentInput"
                    @keydown="onCommentKeydown"
                />

                <div
                    v-if="mentionSuggestions.length"
                    class="absolute left-0 top-full z-10 mt-1 w-56 rounded-md border border-border bg-popover p-1 shadow-md"
                >
                    <button
                        v-for="(member, index) in mentionSuggestions"
                        :key="member.id"
                        type="button"
                        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm"
                        :class="index === activeSuggestionIndex ? 'bg-accent' : 'hover:bg-accent'"
                        @mousedown.prevent="selectMention(member)"
                    >
                        <MemberAvatar :user="member" size="xs" />
                        {{ member.name }}
                    </button>
                </div>

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
