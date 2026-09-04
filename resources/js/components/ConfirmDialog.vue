<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { respondConfirm, useConfirm } from '@/composables/useConfirm';
import {
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
} from 'radix-vue';

const { state } = useConfirm();
</script>

<template>
    <AlertDialogRoot :open="state.open" @update:open="(value) => !value && respondConfirm(false)">
        <AlertDialogPortal>
            <AlertDialogOverlay
                class="fixed inset-0 z-[110] bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <AlertDialogContent
                class="fixed left-1/2 top-1/2 z-[110] grid w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
            >
                <div class="flex flex-col gap-y-1.5 text-center sm:text-left">
                    <AlertDialogTitle class="text-lg font-semibold leading-none tracking-tight">{{ state.title }}</AlertDialogTitle>
                    <AlertDialogDescription v-if="state.description" class="text-sm text-muted-foreground">
                        {{ state.description }}
                    </AlertDialogDescription>
                </div>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <AlertDialogCancel as-child>
                        <Button variant="outline" @click="respondConfirm(false)">{{ state.cancelText }}</Button>
                    </AlertDialogCancel>
                    <AlertDialogAction as-child>
                        <Button :variant="state.variant" @click="respondConfirm(true)">{{ state.confirmText }}</Button>
                    </AlertDialogAction>
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
