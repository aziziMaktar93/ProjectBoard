<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { csrfFetch } from '@/lib/csrfFetch';
import type { AiMessage } from '@/types';
import { router } from '@inertiajs/vue3';
import { Bot, LoaderCircle, Send, X } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';

const props = defineProps<{
    boardId: number;
    aiEnabled: boolean;
    canEdit: boolean;
}>();

const open = ref(false);
const loaded = ref(false);
const loading = ref(false);
const error = ref<string | null>(null);
const draft = ref('');
const messages = ref<AiMessage[]>([]);
const appliedIds = ref<Set<number>>(new Set());
const scrollRef = ref<HTMLElement | null>(null);

async function scrollToBottom() {
    await nextTick();
    scrollRef.value?.scrollTo({ top: scrollRef.value.scrollHeight });
}

async function toggleOpen() {
    open.value = !open.value;

    if (open.value && props.aiEnabled && !loaded.value) {
        await loadConversation();
    }
}

async function loadConversation() {
    loading.value = true;

    try {
        const response = await csrfFetch(route('ai-chat.show', props.boardId));
        const data = await response.json();
        messages.value = data.messages;
        loaded.value = true;
        await scrollToBottom();
    } finally {
        loading.value = false;
    }
}

async function send() {
    const content = draft.value.trim();

    if (!content || loading.value) {
        return;
    }

    draft.value = '';
    error.value = null;
    loading.value = true;

    try {
        const response = await csrfFetch(route('ai-chat.messages.store', props.boardId), {
            method: 'POST',
            body: JSON.stringify({ content }),
        });
        const data = await response.json();

        messages.value.push(data.message);

        if (data.reply) {
            messages.value.push(data.reply);
        } else if (data.error) {
            error.value = data.error;
        }

        await scrollToBottom();
    } finally {
        loading.value = false;
    }
}

async function applyMessage(message: AiMessage) {
    const response = await csrfFetch(route('ai-chat.messages.apply', [props.boardId, message.id]), {
        method: 'POST',
    });
    const data = await response.json();

    if (response.ok) {
        appliedIds.value.add(message.id);
        router.reload({ only: ['board'] });
    } else {
        error.value = data.error ?? 'Could not apply this suggestion.';
    }
}

function isApplied(message: AiMessage): boolean {
    return message.applied_at !== null || appliedIds.value.has(message.id);
}

function actionSummary(message: AiMessage): string {
    if (!message.tool_action) {
        return '';
    }

    if (message.tool_action.type === 'create_lists') {
        return `Create lists: ${message.tool_action.names.join(', ')}`;
    }

    return `Add cards to "${message.tool_action.list_name}": ${message.tool_action.card_names.join(', ')}`;
}
</script>

<template>
    <div class="fixed bottom-4 right-4 z-40">
        <button
            type="button"
            class="flex size-12 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition hover:opacity-90"
            aria-label="AI board assistant"
            @click="toggleOpen"
        >
            <X v-if="open" class="size-5" />
            <Bot v-else class="size-5" />
        </button>

        <div
            v-if="open"
            class="absolute bottom-16 right-0 flex h-[28rem] w-80 flex-col overflow-hidden rounded-xl border border-border bg-background shadow-xl"
        >
            <div class="border-b border-border px-4 py-3">
                <p class="text-sm font-semibold">Board assistant</p>
                <p class="text-xs text-muted-foreground">Brainstorm lists and cards for this board.</p>
            </div>

            <div v-if="!aiEnabled" class="flex flex-1 items-center justify-center p-4 text-center text-sm text-muted-foreground">
                AI chat isn't configured yet.
            </div>

            <template v-else>
                <div ref="scrollRef" class="flex-1 space-y-3 overflow-y-auto p-4">
                    <div v-for="message in messages" :key="message.id" :class="message.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div
                            class="max-w-[85%] rounded-lg px-3 py-2 text-sm"
                            :class="message.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-accent text-accent-foreground'"
                        >
                            <p>{{ message.content }}</p>
                            <div v-if="message.tool_action" class="mt-2 rounded border border-border/50 bg-background/50 p-2">
                                <p class="text-xs">{{ actionSummary(message) }}</p>
                                <Button v-if="canEdit && !isApplied(message)" size="sm" class="mt-1.5 w-full" @click="applyMessage(message)">
                                    Add to board
                                </Button>
                                <p v-else-if="isApplied(message)" class="mt-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                    Added
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="error" class="border-t border-border px-4 py-2 text-xs text-destructive">{{ error }}</p>

                <form class="flex items-center gap-2 border-t border-border p-3" @submit.prevent="send">
                    <input
                        v-model="draft"
                        type="text"
                        placeholder="Ask for list or card ideas..."
                        class="flex-1 rounded-md border border-input bg-transparent px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-ring"
                        :disabled="loading"
                    />
                    <Button type="submit" size="icon" :disabled="loading || !draft.trim()">
                        <LoaderCircle v-if="loading" class="size-4 animate-spin" />
                        <Send v-else class="size-4" />
                    </Button>
                </form>
            </template>
        </div>
    </div>
</template>
