<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { csrfFetch } from '@/lib/csrfFetch';
import type { DashboardMessage } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Bot, LoaderCircle, Send, X } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';

const props = defineProps<{
    aiEnabled: boolean;
    boards: { id: number; name: string }[];
}>();

const open = ref(false);
const loaded = ref(false);
const loading = ref(false);
const error = ref<string | null>(null);
const draft = ref('');
const messages = ref<DashboardMessage[]>([]);
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
        const response = await csrfFetch(route('dashboard-chat.show'));

        if (!response.ok) {
            error.value = 'Could not load the conversation.';
            return;
        }

        const data = await response.json();
        messages.value = data.messages;
        loaded.value = true;
        await scrollToBottom();
    } catch {
        error.value = "Couldn't reach the server, try again.";
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
        const response = await csrfFetch(route('dashboard-chat.messages.store'), {
            method: 'POST',
            body: JSON.stringify({ content }),
        });
        const data = await response.json();

        if (!response.ok && !data.error) {
            error.value = typeof data.message === 'string' ? data.message : 'Could not send that message.';
            draft.value = content;
            return;
        }

        messages.value.push(data.message);

        if (data.reply) {
            messages.value.push(data.reply);
        } else if (data.error) {
            error.value = data.error;
        }

        await scrollToBottom();
    } catch {
        error.value = "Couldn't reach the server, try again.";
        draft.value = content;
    } finally {
        loading.value = false;
    }
}

interface RenderedSegment {
    text: string;
    boardId: number | null;
}

function renderSegments(content: string): RenderedSegment[] {
    const segments: RenderedSegment[] = [];
    const regex = /\[\[([^\]]+)\]\]/g;
    let lastIndex = 0;
    let match: RegExpExecArray | null;

    while ((match = regex.exec(content)) !== null) {
        if (match.index > lastIndex) {
            segments.push({ text: content.slice(lastIndex, match.index), boardId: null });
        }

        const board = props.boards.find((b) => b.name === match![1]);
        segments.push({ text: board ? board.name : match[1], boardId: board ? board.id : null });
        lastIndex = match.index + match[0].length;
    }

    if (lastIndex < content.length) {
        segments.push({ text: content.slice(lastIndex), boardId: null });
    }

    return segments;
}
</script>

<template>
    <div class="fixed bottom-4 right-4 z-40">
        <button
            type="button"
            class="flex size-12 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition hover:opacity-90"
            aria-label="Dashboard assistant"
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
                <p class="text-sm font-semibold">Dashboard assistant</p>
                <p class="text-xs text-muted-foreground">Ask about your progress and stats.</p>
            </div>

            <div v-if="!aiEnabled" class="flex flex-1 items-center justify-center p-4 text-center text-sm text-muted-foreground">
                AI chat isn't configured yet.
            </div>

            <template v-else>
                <div ref="scrollRef" class="flex-1 space-y-3 overflow-y-auto p-4">
                    <div
                        v-for="message in messages"
                        :key="message.id"
                        :class="message.role === 'user' ? 'flex justify-end' : 'flex justify-start'"
                    >
                        <div
                            class="max-w-[85%] rounded-lg px-3 py-2 text-sm"
                            :class="message.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-accent text-accent-foreground'"
                        >
                            <template v-for="(segment, index) in renderSegments(message.content)" :key="index">
                                <Link v-if="segment.boardId" :href="route('boards.show', segment.boardId)" class="font-medium underline">
                                    {{ segment.text }}
                                </Link>
                                <span v-else>{{ segment.text }}</span>
                            </template>
                        </div>
                    </div>
                </div>

                <p v-if="error" class="border-t border-border px-4 py-2 text-xs text-destructive">{{ error }}</p>

                <form class="flex items-center gap-2 border-t border-border p-3" @submit.prevent="send">
                    <input
                        v-model="draft"
                        type="text"
                        placeholder="Ask about your progress..."
                        maxlength="2000"
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
