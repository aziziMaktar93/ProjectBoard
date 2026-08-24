import { reactive } from 'vue';

interface ConfettiPiece {
    id: number;
    left: number;
    color: string;
    delay: number;
    duration: number;
    rotation: number;
    drift: number;
}

interface CelebrationState {
    active: boolean;
    pieces: ConfettiPiece[];
    message: string;
}

const COLORS = ['#f43f5e', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899'];

const MESSAGES = [
    'Checklist complete! 🎉',
    'Semua siap! Mantap!',
    'You crushed it! 💪',
    'Great job, team!',
    'Selesai kerja, bagus!',
    'Boom! 100% done!',
    'Nice work! Keep it up!',
    'Tahniah! Semua selesai!',
    'All tasks done — amazing!',
    "That's how it's done!",
    'Superb effort!',
    'Well done, all checked off!',
];

const state = reactive<CelebrationState>({ active: false, pieces: [], message: MESSAGES[0] });

let nextId = 0;
let hideTimeout: ReturnType<typeof setTimeout> | null = null;

export function celebrate() {
    state.pieces = Array.from({ length: 70 }, () => ({
        id: nextId++,
        left: Math.random() * 100,
        color: COLORS[Math.floor(Math.random() * COLORS.length)],
        delay: Math.random() * 0.3,
        duration: 2 + Math.random() * 1,
        rotation: Math.random() * 360,
        drift: (Math.random() - 0.5) * 200,
    }));
    state.message = MESSAGES[Math.floor(Math.random() * MESSAGES.length)];
    state.active = true;

    if (hideTimeout) {
        clearTimeout(hideTimeout);
    }

    hideTimeout = setTimeout(() => {
        state.active = false;
    }, 3200);
}

export function useCelebration() {
    return { state };
}
