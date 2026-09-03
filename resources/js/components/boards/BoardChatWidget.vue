<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { csrfFetch } from '@/lib/csrfFetch';
import type { BoardMessage } from '@/types';
import { MessageCircle, Send, Trash2, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    boardId: number;
    currentUserId: number;
    members: { id: number; name: string }[];
}>();

const open = ref(false);
const loaded = ref(false);
const loading = ref(false);
const error = ref<string | null>(null);
const draft = ref('');
const messages = ref<BoardMessage[]>([]);
const scrollRef = ref<HTMLElement | null>(null);
const mentionQuery = ref<string | null>(null);
const unreadCount = ref(0);
const lastSeenId = ref(0);
let openPollTimer: ReturnType<typeof setInterval> | null = null;
let closedPollTimer: ReturnType<typeof setInterval> | null = null;

const mentionMatches = computed(() => {
    if (mentionQuery.value === null) {
        return [];
    }

    const query = mentionQuery.value.toLowerCase();

    return props.members.filter((member) => member.name.toLowerCase().startsWith(query)).slice(0, 5);
});

async function scrollToBottom() {
    await nextTick();
    scrollRef.value?.scrollTo({ top: scrollRef.value.scrollHeight });
}

async function toggleOpen() {
    open.value = !open.value;

    if (open.value) {
        stopClosedPolling();
        unreadCount.value = 0;

        if (!loaded.value) {
            await loadMessages();
        }

        if (messages.value.length) {
            lastSeenId.value = messages.value[messages.value.length - 1].id;
        }

        startOpenPolling();
    } else {
        stopOpenPolling();

        if (messages.value.length) {
            lastSeenId.value = messages.value[messages.value.length - 1].id;
        }

        unreadCount.value = 0;
        startClosedPolling();
    }
}

async function loadMessages() {
    loading.value = true;

    try {
        const response = await csrfFetch(route('board-chat.index', props.boardId));
        const data = await response.json();

        if (!response.ok) {
            error.value = 'Could not load the chat.';
            return;
        }

        messages.value = data.messages;
        loaded.value = true;
        await scrollToBottom();
    } catch {
        error.value = "Couldn't reach the server, try again.";
    } finally {
        loading.value = false;
    }
}

async function checkForUnread() {
    if (open.value) {
        return;
    }

    try {
        const response = await csrfFetch(route('board-chat.index', props.boardId));

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        const fetched: BoardMessage[] = data.messages;

        unreadCount.value = fetched.filter((m) => m.id > lastSeenId.value && m.user_id !== props.currentUserId).length;
    } catch {
        // Silent — this is a background refresh, not a user-initiated action.
    }
}

function startClosedPolling() {
    stopClosedPolling();
    closedPollTimer = setInterval(checkForUnread, 15000);
}

function stopClosedPolling() {
    if (closedPollTimer) {
        clearInterval(closedPollTimer);
        closedPollTimer = null;
    }
}

async function pollForNewMessages() {
    try {
        const response = await csrfFetch(route('board-chat.index', props.boardId));

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        const fetched: BoardMessage[] = data.messages;
        const existingIds = new Set(messages.value.map((m) => m.id));
        const hasNew = fetched.some((m) => !existingIds.has(m.id));

        if (hasNew || fetched.length !== messages.value.length) {
            const wasAtBottom = scrollRef.value ? scrollRef.value.scrollHeight - scrollRef.value.scrollTop - scrollRef.value.clientHeight < 40 : true;
            messages.value = fetched;

            if (wasAtBottom) {
                await scrollToBottom();
            }
        }
    } catch {
        // Silent — background refresh.
    }
}

function startOpenPolling() {
    stopOpenPolling();
    openPollTimer = setInterval(pollForNewMessages, 5000);
}

function stopOpenPolling() {
    if (openPollTimer) {
        clearInterval(openPollTimer);
        openPollTimer = null;
    }
}

function onDraftInput() {
    const cursor = draft.value.length;
    const upToCursor = draft.value.slice(0, cursor);
    const match = /@([\w ]*)$/.exec(upToCursor);
    mentionQuery.value = match ? match[1] : null;
}

function selectMention(name: string) {
    draft.value = draft.value.replace(/@([\w ]*)$/, `@${name} `);
    mentionQuery.value = null;
}

async function send() {
    const content = draft.value.trim();

    if (!content || loading.value) {
        return;
    }

    draft.value = '';
    mentionQuery.value = null;
    error.value = null;
    loading.value = true;

    try {
        const response = await csrfFetch(route('board-chat.store', props.boardId), {
            method: 'POST',
            body: JSON.stringify({ content }),
        });
        const data = await response.json();

        if (!response.ok) {
            error.value = typeof data.message === 'string' ? data.message : 'Could not send that message.';
            draft.value = content;
            return;
        }

        messages.value.push(data.message);
        lastSeenId.value = data.message.id;
        await scrollToBottom();
    } catch {
        error.value = "Couldn't reach the server, try again.";
        draft.value = content;
    } finally {
        loading.value = false;
    }
}

async function deleteMessage(message: BoardMessage) {
    if (!confirm('Delete this message?')) {
        return;
    }

    try {
        const response = await csrfFetch(route('board-chat.destroy', [props.boardId, message.id]), {
            method: 'DELETE',
        });

        if (response.ok) {
            messages.value = messages.value.filter((m) => m.id !== message.id);
        } else {
            error.value = 'Could not delete this message.';
        }
    } catch {
        error.value = "Couldn't reach the server, try again.";
    }
}

function renderSegments(content: string): { text: string; mention: boolean }[] {
    const names = props.members.map((member) => member.name).sort((a, b) => b.length - a.length);

    if (names.length === 0) {
        return [{ text: content, mention: false }];
    }

    const pattern = new RegExp(`@(${names.map((name) => name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})`, 'g');
    const segments: { text: string; mention: boolean }[] = [];
    let lastIndex = 0;
    let match: RegExpExecArray | null;

    while ((match = pattern.exec(content)) !== null) {
        if (match.index > lastIndex) {
            segments.push({ text: content.slice(lastIndex, match.index), mention: false });
        }

        segments.push({ text: match[0], mention: true });
        lastIndex = match.index + match[0].length;
    }

    if (lastIndex < content.length) {
        segments.push({ text: content.slice(lastIndex), mention: false });
    }

    return segments;
}

onMounted(() => {
    startClosedPolling();
});

onBeforeUnmount(() => {
    stopClosedPolling();
    stopOpenPolling();
});
</script>

<template>
    <div class="fixed bottom-4 right-20 z-40">
        <button
            type="button"
            class="relative flex size-12 items-center justify-center rounded-full bg-secondary text-secondary-foreground shadow-lg transition hover:opacity-90"
            aria-label="Board chat"
            @click="toggleOpen"
        >
            <X v-if="open" class="size-5" />
            <MessageCircle v-else class="size-5" />
            <span
                v-if="!open && unreadCount > 0"
                class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute bottom-16 right-0 flex h-[28rem] w-80 flex-col overflow-hidden rounded-xl border border-border bg-background shadow-xl"
        >
            <div class="border-b border-border px-4 py-3">
                <p class="text-sm font-semibold">Board chat</p>
                <p class="text-xs text-muted-foreground">Talk with everyone on this board.</p>
            </div>

            <div ref="scrollRef" class="flex-1 space-y-3 overflow-y-auto p-4">
                <div v-for="message in messages" :key="message.id" class="group flex flex-col" :class="message.user_id === currentUserId ? 'items-end' : 'items-start'">
                    <div
                        class="max-w-[85%] rounded-lg px-3 py-2 text-sm"
                        :class="message.user_id === currentUserId ? 'bg-primary text-primary-foreground' : 'bg-accent text-accent-foreground'"
                    >
                        <p class="text-xs font-medium opacity-70">{{ message.user.name }}</p>
                        <p>
                            <template v-for="(segment, index) in renderSegments(message.content)" :key="index">
                                <span v-if="segment.mention" class="font-semibold underline">{{ segment.text }}</span>
                                <template v-else>{{ segment.text }}</template>
                            </template>
                        </p>
                    </div>
                    <button
                        v-if="message.user_id === currentUserId"
                        type="button"
                        class="mt-0.5 hidden text-xs text-muted-foreground hover:text-destructive group-hover:flex"
                        @click="deleteMessage(message)"
                    >
                        <Trash2 class="mr-1 size-3" /> Delete
                    </button>
                </div>
            </div>

            <p v-if="error" class="border-t border-border px-4 py-2 text-xs text-destructive">{{ error }}</p>

            <div class="relative border-t border-border">
                <ul v-if="mentionMatches.length" class="absolute bottom-full left-0 w-full border-b border-border bg-background shadow-sm">
                    <li v-for="member in mentionMatches" :key="member.id">
                        <button
                            type="button"
                            class="block w-full px-3 py-1.5 text-left text-sm hover:bg-accent"
                            @click="selectMention(member.name)"
                        >
                            {{ member.name }}
                        </button>
                    </li>
                </ul>

                <form class="flex items-center gap-2 p-3" @submit.prevent="send">
                    <input
                        v-model="draft"
                        type="text"
                        placeholder="Message the board..."
                        class="flex-1 rounded-md border border-input bg-transparent px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-ring"
                        :disabled="loading"
                        @input="onDraftInput"
                    />
                    <Button type="submit" size="icon" :disabled="loading || !draft.trim()">
                        <Send class="size-4" />
                    </Button>
                </form>
            </div>
        </div>
    </div>
</template>
