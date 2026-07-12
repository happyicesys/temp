<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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
import { computed, ref } from 'vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

type Trend = 'up' | 'down' | 'flat';
type Tone = 'neutral' | 'good' | 'warn';
type DeviceStatus = 'ok' | 'warn' | 'offline';
type Range = '24h' | '7d' | '30d';

interface Stat {
    key: string;
    label: string;
    value: string;
    sub: string;
    delta: string;
    trend: Trend;
    tone: Tone;
}

interface Chart {
    series: number[];
    current: string | null;
    unit: string;
    delta: string | null;
    trend: Trend;
    axisLabels: string[];
}

interface DeviceRow {
    id: number;
    name: string;
    location: string | null;
    value: number | null;
    status: DeviceStatus;
    recordedAt: string | null;
}

const props = defineProps<{
    range: Range;
    stats: Stat[];
    chart: Chart;
    devices: DeviceRow[];
}>();

const ranges: Range[] = ['24h', '7d', '30d'];
const loading = ref(false);

/** Re-query the backend for a different window without a full page reload. */
function selectRange(range: Range): void {
    if (range === props.range || loading.value) {
        return;
    }

    router.get(
        dashboard.url({ query: { range } }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['range', 'stats', 'chart', 'devices'],
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

/** Icons are component references, so they are mapped client-side by stat key. */
const statIcons: Record<string, typeof Thermometer> = {
    devices: Thermometer,
    active: Activity,
    alerts: TriangleAlert,
    avg: Snowflake,
};

// --- Chart geometry (responsive via viewBox) --------------------------------
const W = 600;
const H = 200;
const PAD = 12;

const series = computed(() => props.chart.series);
const hasSeries = computed(() => series.value.length >= 2);
const chartMin = computed(() => Math.min(...series.value));
const chartMax = computed(() => Math.max(...series.value));

function px(i: number): number {
    return PAD + (i / (series.value.length - 1)) * (W - PAD * 2);
}
function py(v: number): number {
    const lo = chartMin.value - 1;
    const hi = chartMax.value + 1;
    const span = hi - lo || 1;

    return PAD + (1 - (v - lo) / span) * (H - PAD * 2);
}

// Smooth line using midpoint quadratic curves.
const linePath = computed(() => {
    const pts = series.value.map((v, i) => [px(i), py(v)] as const);
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
    DeviceStatus,
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

const toneRing: Record<Tone, string> = {
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
                    :disabled="loading"
                    class="rounded-md px-3 py-1.5 font-medium transition-colors disabled:opacity-60"
                    :class="
                        r === props.range
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="selectRange(r)"
                >
                    {{ r }}
                </button>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <div
                v-for="stat in props.stats"
                :key="stat.key"
                class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="flex size-9 items-center justify-center rounded-lg"
                        :class="toneRing[stat.tone]"
                    >
                        <component
                            :is="statIcons[stat.key] ?? Thermometer"
                            class="size-4.5"
                        />
                    </span>
                    <span
                        v-if="stat.delta"
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
                            Fleet average · last {{ props.range }}
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-semibold tabular-nums">
                            {{
                                props.chart.current === null
                                    ? '—'
                                    : `${props.chart.current}${props.chart.unit}`
                            }}
                        </div>
                        <div
                            v-if="props.chart.delta"
                            class="inline-flex items-center gap-0.5 text-xs font-medium"
                            :class="
                                props.chart.trend === 'down'
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-muted-foreground'
                            "
                        >
                            <ArrowDownRight
                                v-if="props.chart.trend === 'down'"
                                class="size-3.5"
                            />
                            <ArrowUpRight
                                v-else-if="props.chart.trend === 'up'"
                                class="size-3.5"
                            />
                            {{ props.chart.delta }}° vs prev
                        </div>
                    </div>
                </div>

                <svg
                    v-if="hasSeries"
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
                <div
                    v-else
                    class="flex h-40 w-full items-center justify-center text-sm text-muted-foreground sm:h-56"
                >
                    No readings in this window yet.
                </div>

                <div
                    v-if="hasSeries"
                    class="flex justify-between text-xs text-muted-foreground"
                >
                    <span v-for="(label, i) in props.chart.axisLabels" :key="i">
                        {{ label }}
                    </span>
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
                <ul v-if="props.devices.length" class="divide-y divide-border">
                    <li
                        v-for="device in props.devices"
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
                                v-if="device.location"
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
                                {{ device.recordedAt ?? 'No data' }}
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
                <div
                    v-else
                    class="flex flex-1 items-center justify-center px-4 py-10 text-sm text-muted-foreground"
                >
                    No devices registered yet.
                </div>
            </div>
        </div>
    </div>
</template>
