<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Activity,
    ArrowDownRight,
    ArrowUpRight,
    ChevronRight,
    MapPin,
    Snowflake,
    Thermometer,
    TriangleAlert,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

/**
 * NOTE: Visual-first placeholder data. When the backend is ready, replace this
 * block with `const props = defineProps<DashboardProps>()` fed by a
 * DashboardController, keeping the same shapes below.
 */
type Trend = 'up' | 'down' | 'flat';

interface Stat {
    key: string;
    label: string;
    value: string;
    sub: string;
    delta: string;
    trend: Trend;
    icon: typeof Thermometer;
    tone: 'neutral' | 'good' | 'warn';
}

interface DeviceRow {
    id: number;
    name: string;
    location: string;
    value: number | null;
    status: 'ok' | 'warn' | 'offline';
    recordedAt: string;
}

const ranges = ['24h', '7d', '30d'] as const;
const activeRange = '24h';

const stats: Stat[] = [
    {
        key: 'devices',
        label: 'Devices',
        value: '24',
        sub: 'monitored',
        delta: '+2',
        trend: 'up',
        icon: Thermometer,
        tone: 'neutral',
    },
    {
        key: 'active',
        label: 'Reporting now',
        value: '21',
        sub: 'online',
        delta: '88%',
        trend: 'up',
        icon: Activity,
        tone: 'good',
    },
    {
        key: 'alerts',
        label: 'Active alerts',
        value: '3',
        sub: 'breaching',
        delta: '+1',
        trend: 'up',
        icon: TriangleAlert,
        tone: 'warn',
    },
    {
        key: 'avg',
        label: 'Avg chamber',
        value: '4.2°',
        sub: 'last hour',
        delta: '-0.4°',
        trend: 'down',
        icon: Snowflake,
        tone: 'neutral',
    },
];

// Sample 24h chamber-temperature trend (°C).
const series = [
    5.1, 4.8, 4.6, 4.9, 5.3, 5.0, 4.4, 4.1, 3.9, 4.2, 4.7, 5.2, 6.1, 6.4, 5.8,
    5.1, 4.6, 4.3, 4.0, 4.2, 4.5, 4.1, 3.8, 4.2,
];

const devices: DeviceRow[] = [
    {
        id: 1,
        name: 'Cold Room A',
        location: 'Kitchen · Level 1',
        value: 3.8,
        status: 'ok',
        recordedAt: '2 min ago',
    },
    {
        id: 2,
        name: 'Display Fridge 3',
        location: 'Front of house',
        value: 7.9,
        status: 'warn',
        recordedAt: '1 min ago',
    },
    {
        id: 3,
        name: 'Freezer B2',
        location: 'Storeroom',
        value: -18.4,
        status: 'ok',
        recordedAt: '4 min ago',
    },
    {
        id: 4,
        name: 'Walk-in Chiller',
        location: 'Loading dock',
        value: null,
        status: 'offline',
        recordedAt: '46 min ago',
    },
];

// --- Chart geometry (responsive via viewBox) --------------------------------
const W = 600;
const H = 200;
const PAD = 12;

const chartMin = computed(() => Math.min(...series));
const chartMax = computed(() => Math.max(...series));

function px(i: number): number {
    return PAD + (i / (series.length - 1)) * (W - PAD * 2);
}
function py(v: number): number {
    const lo = chartMin.value - 1;
    const hi = chartMax.value + 1;

    return PAD + (1 - (v - lo) / (hi - lo)) * (H - PAD * 2);
}

// Smooth line using midpoint quadratic curves.
const linePath = computed(() => {
    const pts = series.map((v, i) => [px(i), py(v)] as const);
    let d = `M${pts[0][0]},${pts[0][1]}`;

    for (let i = 1; i < pts.length; i++) {
        const [x0, y0] = pts[i - 1];
        const [x1, y1] = pts[i];
        const mx = (x0 + x1) / 2;
        d += ` Q${x0},${y0} ${mx},${(y0 + y1) / 2} T${x1},${y1}`;
    }

    return d;
});

const areaPath = computed(
    () => `${linePath.value} L${W - PAD},${H - PAD} L${PAD},${H - PAD} Z`,
);

const statusMeta: Record<
    DeviceRow['status'],
    { label: string; class: string; dot: string }
> = {
    ok: {
        label: 'Normal',
        class: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        dot: 'bg-emerald-500',
    },
    warn: {
        label: 'Alert',
        class: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        dot: 'bg-amber-500',
    },
    offline: {
        label: 'Offline',
        class: 'bg-muted text-muted-foreground',
        dot: 'bg-muted-foreground',
    },
};

const toneRing: Record<Stat['tone'], string> = {
    neutral: 'text-foreground bg-muted',
    good: 'text-emerald-600 bg-emerald-500/15 dark:text-emerald-400',
    warn: 'text-amber-600 bg-amber-500/15 dark:text-amber-400',
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">
                    Overview
                </h1>
                <p class="text-sm text-muted-foreground">
                    Live temperature health across your fleet.
                </p>
            </div>
            <div
                class="inline-flex self-start rounded-lg border border-border bg-card p-0.5 text-sm shadow-sm"
            >
                <button
                    v-for="r in ranges"
                    :key="r"
                    type="button"
                    class="rounded-md px-3 py-1.5 font-medium transition-colors"
                    :class="
                        r === activeRange
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                >
                    {{ r }}
                </button>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <div
                v-for="stat in stats"
                :key="stat.key"
                class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="flex size-9 items-center justify-center rounded-lg"
                        :class="toneRing[stat.tone]"
                    >
                        <component :is="stat.icon" class="size-4.5" />
                    </span>
                    <span
                        class="inline-flex items-center gap-0.5 text-xs font-medium"
                        :class="
                            stat.trend === 'down'
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-muted-foreground'
                        "
                    >
                        <ArrowUpRight
                            v-if="stat.trend === 'up'"
                            class="size-3.5"
                        />
                        <ArrowDownRight
                            v-else-if="stat.trend === 'down'"
                            class="size-3.5"
                        />
                        {{ stat.delta }}
                    </span>
                </div>
                <div>
                    <div
                        class="text-2xl font-semibold tracking-tight tabular-nums"
                    >
                        {{ stat.value }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{ stat.label }} · {{ stat.sub }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart + recent devices -->
        <div class="grid gap-4 sm:gap-6 lg:grid-cols-5">
            <!-- Trend -->
            <div
                class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5 lg:col-span-3"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold tracking-tight">
                            Chamber temperature
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Fleet average · last {{ activeRange }}
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-semibold tabular-nums">
                            4.2°C
                        </div>
                        <div
                            class="inline-flex items-center gap-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                        >
                            <ArrowDownRight class="size-3.5" /> 0.4° vs prev
                        </div>
                    </div>
                </div>

                <svg
                    :viewBox="`0 0 ${W} ${H}`"
                    preserveAspectRatio="none"
                    class="h-40 w-full sm:h-56"
                    role="img"
                    aria-label="Temperature trend chart"
                >
                    <defs>
                        <linearGradient
                            id="tempFill"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="0%"
                                stop-color="var(--primary)"
                                stop-opacity="0.18"
                            />
                            <stop
                                offset="100%"
                                stop-color="var(--primary)"
                                stop-opacity="0"
                            />
                        </linearGradient>
                    </defs>
                    <path :d="areaPath" fill="url(#tempFill)" />
                    <path
                        :d="linePath"
                        fill="none"
                        stroke="var(--primary)"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        vector-effect="non-scaling-stroke"
                    />
                </svg>

                <div class="flex justify-between text-xs text-muted-foreground">
                    <span>00:00</span>
                    <span class="hidden sm:inline">06:00</span>
                    <span>12:00</span>
                    <span class="hidden sm:inline">18:00</span>
                    <span>Now</span>
                </div>
            </div>

            <!-- Recent devices -->
            <div
                class="flex flex-col rounded-xl border border-border bg-card shadow-sm lg:col-span-2"
            >
                <div
                    class="flex items-center justify-between border-b border-border px-4 py-3.5"
                >
                    <h2 class="font-semibold tracking-tight">Devices</h2>
                    <a
                        href="/devices"
                        class="inline-flex items-center gap-0.5 text-sm font-medium text-primary hover:underline"
                    >
                        All <ChevronRight class="size-4" />
                    </a>
                </div>
                <ul class="divide-y divide-border">
                    <li
                        v-for="device in devices"
                        :key="device.id"
                        class="flex items-center gap-3 px-4 py-3"
                    >
                        <span
                            class="size-2 shrink-0 rounded-full"
                            :class="statusMeta[device.status].dot"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium">
                                {{ device.name }}
                            </div>
                            <div
                                class="flex items-center gap-1 truncate text-xs text-muted-foreground"
                            >
                                <MapPin class="size-3 shrink-0" />
                                <span class="truncate">{{
                                    device.location
                                }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-semibold tabular-nums">
                                {{
                                    device.value === null
                                        ? '—'
                                        : `${device.value.toFixed(1)}°`
                                }}
                            </div>
                            <div class="text-[11px] text-muted-foreground">
                                {{ device.recordedAt }}
                            </div>
                        </div>
                        <span
                            class="hidden shrink-0 rounded-full px-2 py-0.5 text-xs font-medium sm:inline-block"
                            :class="statusMeta[device.status].class"
                        >
                            {{ statusMeta[device.status].label }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
