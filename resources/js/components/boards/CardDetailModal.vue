<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MemberAvatar from '@/components/MemberAvatar.vue';
import CardActivityFeed from '@/components/boards/CardActivityFeed.vue';
import CardAttachmentList from '@/components/boards/CardAttachmentList.vue';
import CardChecklist from '@/components/boards/CardChecklist.vue';
import CardLabelPicker from '@/components/boards/CardLabelPicker.vue';
import CardMemberPicker from '@/components/boards/CardMemberPicker.vue';
import ColorSwatchPicker from '@/components/boards/ColorSwatchPicker.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { showToast } from '@/composables/useToast';
import { stripGradient } from '@/lib/colorGradient';
import { isCardChecklistComplete } from '@/lib/cardCompletion';
import type { Card, CardAttachment, CardLabel, User } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Image, Paintbrush, Paperclip, SquareCheck, Tag, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    card: Card | null;
    boardId: number;
    boardMembers: User[];
    boardLabels: CardLabel[];
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: '',
    description: '' as string | null,
});

watch(
    open,
    (isOpen: boolean) => {
        if (isOpen && props.card) {
            form.name = props.card.name;
            form.description = props.card.description;
        }
    },
    { immediate: true },
);

function submit() {
    if (!props.card) {
        return;
    }

    form.patch(route('cards.update', props.card.id), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            showToast('Card updated');
        },
    });
}

function setDueDate(value: string) {
    if (!props.card) {
        return;
    }

    router.patch(route('cards.update', props.card.id), { due_date: value || null }, { preserveScroll: true });
}

const colorModel = computed<string | null>({
    get: () => props.card?.color ?? null,
    set: (value: string | null) => {
        if (!props.card) {
            return;
        }

        router.patch(route('cards.update', props.card.id), { color: value }, { preserveScroll: true });
    },
});

function isImage(attachment: CardAttachment): boolean {
    return attachment.mime_type.startsWith('image/');
}

const imageAttachments = computed(() => (props.card?.attachments ?? []).filter(isImage));

function setCover(attachmentId: number | null) {
    if (!props.card) {
        return;
    }

    router.patch(route('cards.update', props.card.id), { cover_attachment_id: attachmentId }, { preserveScroll: true });
}

const checklists = computed(() => [...(props.card?.checklists ?? [])].sort((a, b) => a.position - b.position));

const showAddChecklist = ref(false);

const addChecklistForm = useForm({
    name: '',
});

function submitAddChecklist() {
    if (!props.card || !addChecklistForm.name.trim()) {
        return;
    }

    addChecklistForm.post(route('checklists.store', props.card.id), {
        preserveScroll: true,
        onSuccess: () => {
            addChecklistForm.reset();
            showAddChecklist.value = false;
        },
    });
}

const attachmentListRef = ref<InstanceType<typeof CardAttachmentList> | null>(null);

const dueDateLabel = computed(() => {
    if (!props.card?.due_date) {
        return null;
    }

    return new Date(`${props.card.due_date}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
});

const isOverdue = computed(
    () =>
        !!props.card?.due_date &&
        props.card.due_date < new Date().toISOString().slice(0, 10) &&
        !isCardChecklistComplete(props.card),
);

const gridMaxHeightClass = computed(() => (props.card?.cover_attachment ? 'max-h-[calc(85vh-15rem)]' : 'max-h-[calc(85vh-5rem)]'));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="card" class="max-h-[85vh] overflow-hidden sm:max-w-6xl">
            <img
                v-if="card.cover_attachment"
                :src="route('card-attachments.view', card.cover_attachment.id)"
                :alt="card.cover_attachment.name"
                class="-mx-6 -mt-6 h-40 w-[calc(100%+3rem)] object-cover sm:rounded-t-lg"
            />
            <div
                v-else-if="card.color"
                class="-mx-6 -mt-6 h-3 sm:rounded-t-lg"
                :style="{ backgroundImage: stripGradient(card.color) }"
            />

            <DialogHeader>
                <DialogTitle>Edit card</DialogTitle>
            </DialogHeader>

            <div class="grid grid-cols-1 gap-6 overflow-hidden md:grid-cols-[1fr_1fr]" :class="gridMaxHeightClass">
                <div class="space-y-5 overflow-y-auto pr-1">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Input id="card-name" v-model="form.name" required class="text-base font-medium" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <textarea
                                id="card-description"
                                v-model="form.description"
                                rows="3"
                                placeholder="Add a more detailed description..."
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="flex justify-end">
                            <Button type="submit" size="sm" :disabled="form.processing">Save</Button>
                        </div>
                    </form>

                    <div class="flex flex-wrap items-center gap-1.5 border-t border-neutral-200 pt-4 dark:border-neutral-700">
                        <Popover>
                            <PopoverTrigger as-child>
                                <Button variant="outline" size="sm"><Users class="size-3.5" /> Members</Button>
                            </PopoverTrigger>
                            <PopoverContent>
                                <p class="mb-2 text-xs font-semibold text-muted-foreground">Members</p>
                                <CardMemberPicker :card="card" :board-members="boardMembers" />
                            </PopoverContent>
                        </Popover>

                        <Popover>
                            <PopoverTrigger as-child>
                                <Button variant="outline" size="sm"><Tag class="size-3.5" /> Labels</Button>
                            </PopoverTrigger>
                            <PopoverContent>
                                <p class="mb-2 text-xs font-semibold text-muted-foreground">Labels</p>
                                <CardLabelPicker :card="card" :board-id="boardId" :board-labels="boardLabels" />
                            </PopoverContent>
                        </Popover>

                        <Popover>
                            <PopoverTrigger as-child>
                                <Button variant="outline" size="sm"><CalendarDays class="size-3.5" /> Dates</Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-64">
                                <p class="mb-2 text-xs font-semibold text-muted-foreground">Due date</p>
                                <div class="flex items-center gap-2">
                                    <Input
                                        type="date"
                                        :model-value="card.due_date ?? ''"
                                        @change="setDueDate(($event.target as HTMLInputElement).value)"
                                    />
                                    <Button v-if="card.due_date" type="button" variant="ghost" size="sm" @click="setDueDate('')">
                                        Clear
                                    </Button>
                                </div>
                            </PopoverContent>
                        </Popover>

                        <Popover v-model:open="showAddChecklist">
                            <PopoverTrigger as-child>
                                <Button variant="outline" size="sm"><SquareCheck class="size-3.5" /> Checklist</Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-64">
                                <p class="mb-2 text-xs font-semibold text-muted-foreground">Add checklist</p>
                                <form class="flex gap-2" @submit.prevent="submitAddChecklist">
                                    <Input v-model="addChecklistForm.name" placeholder="Checklist name" autofocus />
                                    <Button type="submit" size="sm" :disabled="addChecklistForm.processing">Add</Button>
                                </form>
                            </PopoverContent>
                        </Popover>

                        <Button variant="outline" size="sm" @click="attachmentListRef?.pickFile()">
                            <Paperclip class="size-3.5" /> Attachment
                        </Button>

                        <Popover>
                            <PopoverTrigger as-child>
                                <Button variant="outline" size="sm"><Paintbrush class="size-3.5" /> Color</Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-56">
                                <p class="mb-2 text-xs font-semibold text-muted-foreground">Color</p>
                                <ColorSwatchPicker v-model="colorModel" />
                            </PopoverContent>
                        </Popover>

                        <Popover>
                            <PopoverTrigger as-child>
                                <Button variant="outline" size="sm"><Image class="size-3.5" /> Cover</Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-64">
                                <p class="mb-2 text-xs font-semibold text-muted-foreground">Cover image</p>
                                <p v-if="!imageAttachments.length" class="text-xs text-muted-foreground">
                                    Add an image attachment first to use it as a cover.
                                </p>
                                <div v-else class="grid grid-cols-3 gap-2">
                                    <button
                                        v-for="attachment in imageAttachments"
                                        :key="attachment.id"
                                        type="button"
                                        class="aspect-video overflow-hidden rounded-md ring-2 ring-offset-1"
                                        :class="card.cover_attachment_id === attachment.id ? 'ring-primary' : 'ring-transparent hover:ring-neutral-300'"
                                        :aria-label="`Use ${attachment.name} as cover`"
                                        @click="setCover(attachment.id)"
                                    >
                                        <img :src="route('card-attachments.view', attachment.id)" :alt="attachment.name" class="size-full object-cover" />
                                    </button>
                                </div>
                                <Button
                                    v-if="card.cover_attachment_id"
                                    variant="ghost"
                                    size="sm"
                                    class="mt-2 w-full"
                                    @click="setCover(null)"
                                >
                                    Remove cover
                                </Button>
                            </PopoverContent>
                        </Popover>
                    </div>

                    <div
                        v-if="dueDateLabel"
                        class="inline-flex w-fit items-center gap-1 rounded px-1.5 py-0.5 text-xs"
                        :class="
                            isOverdue
                                ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'
                                : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300'
                        "
                    >
                        <CalendarDays class="size-3.5" />
                        <span>{{ dueDateLabel }}</span>
                    </div>

                    <div v-if="card.members?.length" class="flex flex-wrap items-center gap-1.5">
                        <MemberAvatar v-for="member in card.members" :key="member.id" :user="member" size="xs" />
                    </div>

                    <div v-if="card.labels?.length" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="label in card.labels"
                            :key="label.id"
                            class="rounded px-2 py-0.5 text-xs font-medium text-white drop-shadow-sm"
                            :style="{ backgroundColor: label.color }"
                        >
                            {{ label.name }}
                        </span>
                    </div>

                    <div v-if="checklists.length" class="space-y-3">
                        <CardChecklist v-for="item in checklists" :key="item.id" :checklist="item" />
                    </div>

                    <div v-if="card.attachments?.length" class="space-y-2">
                        <Label class="text-xs font-semibold text-muted-foreground">Attachments</Label>
                        <CardAttachmentList ref="attachmentListRef" :card="card" />
                    </div>
                    <CardAttachmentList v-else ref="attachmentListRef" :card="card" />
                </div>

                <div
                    class="space-y-3 overflow-y-auto border-t border-neutral-200 pt-4 md:border-t-0 md:border-l md:pl-4 md:pt-0 dark:border-neutral-700"
                >
                    <Label>Comments and activity</Label>
                    <CardActivityFeed :card="card" :board-members="boardMembers" />
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
