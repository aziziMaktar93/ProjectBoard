import { computed, ref } from 'vue';

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

export function formatDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function useMonthCalendar() {
    const current = ref(new Date());

    const monthLabel = computed(() => current.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));

    const todayKey = formatDate(new Date());

    const gridDays = computed(() => {
        const year = current.value.getFullYear();
        const month = current.value.getMonth();

        const firstOfMonth = new Date(year, month, 1);
        const gridStart = new Date(year, month, 1 - firstOfMonth.getDay());

        const lastOfMonth = new Date(year, month + 1, 0);
        const gridEnd = new Date(year, month, lastOfMonth.getDate() + (6 - lastOfMonth.getDay()));

        const days: { date: Date; key: string; inMonth: boolean }[] = [];
        const cursor = new Date(gridStart);

        while (cursor <= gridEnd) {
            days.push({ date: new Date(cursor), key: formatDate(cursor), inMonth: cursor.getMonth() === month });
            cursor.setDate(cursor.getDate() + 1);
        }

        return days;
    });

    function goToMonth(offset: number) {
        current.value = new Date(current.value.getFullYear(), current.value.getMonth() + offset, 1);
    }

    function goToToday() {
        current.value = new Date();
    }

    return { WEEKDAYS, current, monthLabel, todayKey, gridDays, goToMonth, goToToday };
}
