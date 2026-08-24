<script setup lang="ts">
import { useCelebration } from '@/composables/useCelebration';

const { state } = useCelebration();
</script>

<template>
    <Teleport to="body">
        <Transition name="celebration-fade">
            <div v-if="state.active" class="pointer-events-none fixed inset-0 z-[200] overflow-hidden">
                <span
                    v-for="piece in state.pieces"
                    :key="piece.id"
                    class="confetti-piece"
                    :style="{
                        left: `${piece.left}%`,
                        backgroundColor: piece.color,
                        animationDelay: `${piece.delay}s`,
                        animationDuration: `${piece.duration}s`,
                        '--drift': `${piece.drift}px`,
                        '--rotation': `${piece.rotation}deg`,
                    }"
                />

                <div class="celebration-badge">
                    <span class="text-4xl">🎉</span>
                    <p class="whitespace-nowrap rounded-full bg-black/70 px-3 py-1 text-sm font-semibold text-white">{{ state.message }}</p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.confetti-piece {
    position: absolute;
    top: -10px;
    width: 8px;
    height: 14px;
    opacity: 0.9;
    animation-name: confetti-fall;
    animation-timing-function: ease-in;
    animation-fill-mode: forwards;
}

@keyframes confetti-fall {
    0% {
        transform: translate(0, 0) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translate(var(--drift), 110vh) rotate(var(--rotation));
        opacity: 0;
    }
}

.celebration-badge {
    position: absolute;
    top: 18%;
    left: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.375rem;
    transform: translate(-50%, -50%);
    animation: celebration-pop 0.4s ease-out;
}

@keyframes celebration-pop {
    0% {
        transform: translate(-50%, -50%) scale(0.5);
        opacity: 0;
    }
    60% {
        transform: translate(-50%, -50%) scale(1.15);
        opacity: 1;
    }
    100% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 1;
    }
}

.celebration-badge p {
    box-shadow: 0 4px 16px rgb(0 0 0 / 0.25);
}

.celebration-fade-leave-active {
    transition: opacity 0.4s ease;
}

.celebration-fade-leave-to {
    opacity: 0;
}
</style>
