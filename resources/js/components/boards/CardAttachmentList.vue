<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import type { Card, CardAttachment } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { Download, Eye, Image, ImageOff, Paperclip, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    card: Card;
}>();

const PREVIEWABLE_MIME_TYPES = ['application/pdf'];

function isPreviewable(attachment: CardAttachment): boolean {
    return PREVIEWABLE_MIME_TYPES.includes(attachment.mime_type) || attachment.mime_type.startsWith('image/');
}

function isImage(attachment: CardAttachment): boolean {
    return attachment.mime_type.startsWith('image/');
}

function setCover(attachmentId: number | null) {
    router.patch(route('cards.update', props.card.id), { cover_attachment_id: attachmentId }, { preserveScroll: true });
}

const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<{ file: File | null }>({
    file: null,
});

function pickFile() {
    fileInput.value?.click();
}

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    if (!file || !props.card) {
        return;
    }

    form.file = file;
    form.post(route('card-attachments.store', props.card.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            target.value = '';
        },
    });
}

defineExpose({ pickFile });

function deleteAttachment(attachmentId: number) {
    if (!confirm('Delete this attachment? This cannot be undone.')) {
        return;
    }

    router.delete(route('card-attachments.destroy', attachmentId), { preserveScroll: true });
}

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
</script>

<template>
    <div class="space-y-2">
        <ul v-if="card.attachments?.length" class="space-y-1">
            <li
                v-for="attachment in card.attachments"
                :key="attachment.id"
                class="group flex items-center gap-2 rounded-md border border-neutral-200 p-2 text-sm dark:border-neutral-700"
            >
                <Paperclip class="size-4 shrink-0 text-muted-foreground" />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ attachment.name }}</p>
                    <p class="text-xs text-muted-foreground">{{ formatSize(attachment.size) }}</p>
                </div>
                <Tooltip v-if="isPreviewable(attachment)">
                    <TooltipTrigger as-child>
                        <a
                            :href="route('card-attachments.view', attachment.id)"
                            target="_blank"
                            rel="noopener"
                            class="text-muted-foreground opacity-0 hover:text-foreground group-hover:opacity-100"
                            aria-label="View attachment"
                        >
                            <Eye class="size-3.5" />
                        </a>
                    </TooltipTrigger>
                    <TooltipContent>View</TooltipContent>
                </Tooltip>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <a
                            :href="route('card-attachments.download', attachment.id)"
                            class="text-muted-foreground opacity-0 hover:text-foreground group-hover:opacity-100"
                            aria-label="Download attachment"
                        >
                            <Download class="size-3.5" />
                        </a>
                    </TooltipTrigger>
                    <TooltipContent>Download</TooltipContent>
                </Tooltip>
                <Tooltip v-if="isImage(attachment)">
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            class="opacity-0 group-hover:opacity-100"
                            :class="
                                card.cover_attachment_id === attachment.id
                                    ? 'text-primary opacity-100'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :aria-label="card.cover_attachment_id === attachment.id ? 'Remove cover' : 'Set as cover'"
                            @click="setCover(card.cover_attachment_id === attachment.id ? null : attachment.id)"
                        >
                            <ImageOff v-if="card.cover_attachment_id === attachment.id" class="size-3.5" />
                            <Image v-else class="size-3.5" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>{{ card.cover_attachment_id === attachment.id ? 'Remove cover' : 'Set as cover' }}</TooltipContent>
                </Tooltip>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            class="text-muted-foreground opacity-0 hover:text-destructive group-hover:opacity-100"
                            aria-label="Delete attachment"
                            @click="deleteAttachment(attachment.id)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>Delete</TooltipContent>
                </Tooltip>
            </li>
        </ul>

        <input ref="fileInput" type="file" class="hidden" @change="onFileChange" />
    </div>
</template>
