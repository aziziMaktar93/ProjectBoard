import { reactive } from 'vue';

export interface ToastMessage {
    id: number;
    message: string;
    variant: 'success' | 'error';
}

const toasts = reactive<ToastMessage[]>([]);
let nextId = 0;

export function showToast(message: string, variant: ToastMessage['variant'] = 'success') {
    const id = nextId++;
    toasts.push({ id, message, variant });

    setTimeout(() => {
        const index = toasts.findIndex((toast) => toast.id === id);

        if (index !== -1) {
            toasts.splice(index, 1);
        }
    }, 3000);
}

export function useToast() {
    return { toasts, showToast };
}
