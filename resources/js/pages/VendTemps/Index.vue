<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import VendTempChart from '@/components/VendTempChart.vue';

interface Reading {
    temperature: number | null;
    humidity: number | null;
    recorded_at: string | null;
}

interface DeviceSummary {
    id: number;
    name: string | null;
    asset_code: string | null;
}

const props = defineProps<{
    device: {
        id: number;
        name: string | null;
        asset_code: string | null;
        location: string | null;
    };
    devices: DeviceSummary[];
    readings: Reading[];
    filters: { datetime_from: string; datetime_to: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Temperature', href: '#' }],
    },
});

const TEMPERATURE_COLOR = '#0ea5e9'; // sky
const HUMIDITY_COLOR = '#f59e0b'; // amber

interface ChartPoint {
    t: number;
    v: number | null;
}

interface ChartSeries {
    label: string;
    color: string;
    unit: string;
    axis: 'left' | 'right';
    points: ChartPoint[];
}

// Humidity is an opt-in second series on its own axis.
const showHumidity = ref(true);

function pointsFor(metric: 'temperature' | 'humidity'): ChartPoint[] {
    return props.readings
        .filter((r) => r.recorded_at !== null && r[metric] !== null)
        .map((r) => ({ t: Date.parse(r.recorded_at as string), v: r[metric] }))
        .sort((a, b) => a.t - b.t);
}

const series = computed<ChartSeries[]>(() => {
    const built: ChartSeries[] = [
        {
            label: 'Temperature',
            color: TEMPERATURE_COLOR,
            unit: '°C',
            axis: 'left' as const,
            points: pointsFor('temperature'),
        },
    ];

    if (showHumidity.value) {
        built.push({
            label: 'Humidity',
            color: HUMIDITY_COLOR,
            unit: '%',
            axis: 'right' as const,
            points: pointsFor('humidity'),
        });
    }

    return built;
});

function summarize(metric: 'temperature' | 'humidity') {
    const values = props.readings
        .filter((r) => r[metric] !== null)
        .map((r) => r[metric] as number);

    return {
        count: values.length,
        min: values.length ? Math.min(...values) : null,
        max: values.length ? Math.max(...values) : null,
        last: values.length ? values[values.length - 1] : null,
    };
}

const temperatureStats = computed(() => summarize('temperature'));
const humidityStats = computed(() => summarize('humidity'));

const rangeLabel = computed(
    () =>
        `${formatLabel(props.filters.datetime_from)} → ${formatLabel(props.filters.datetime_to)}`,
);

function formatLabel(iso: string): string {
    const d = new Date(iso);

    return Number.isNaN(d.getTime())
        ? iso
        : d.toLocaleString(undefined, {
              month: 'short',
              day: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          });
}

function toLocalInput(iso: string): string {
    const d = new Date(iso);

    if (Number.isNaN(d.getTime())) {
        return '';
    }

    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const fromInput = ref(toLocalInput(props.filters.datetime_from));
const toInput = ref(toLocalInput(props.filters.datetime_to));

type Query = Record<string, string>;

function navigate(query: Query): void {
    router.get(`/devices/${props.device.id}/vend-temps`, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function currentQuery(): Query {
    return {
        datetime_from: new Date(fromInput.value).toISOString(),
        datetime_to: new Date(toInput.value).toISOString(),
    };
}

const shortcuts: { label: string; hours: number }[] = [
    { label: '1h', hours: 1 },
    { label: '2h', hours: 2 },
    { label: '3h', hours: 3 },
    { label: '6h', hours: 6 },
    { label: '12h', hours: 12 },
    { label: '1d', hours: 24 },
    { label: '2d', hours: 48 },
    { label: '7d', hours: 168 },
    { label: '14d', hours: 336 },
];

function applyShortcut(hours: number): void {
    const to = new Date();
    const from = new Date(to.getTime() - hours * 3600 * 1000);
    fromInput.value = toLocalInput(from.toISOString());
    toInput.value = toLocalInput(to.toISOString());
    navigate({
        datetime_from: from.toISOString(),
        datetime_to: to.toISOString(),
    });
}

function applyCustomRange(): void {
    navigate(currentQuery());
}

function onDeviceChange(event: Event): void {
    const id = (event.target as HTMLSelectElement).value;
    router.get(`/devices/${id}/vend-temps`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Temperature" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <!-- Header -->
        <div
            class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ device.name ?? 'Device' }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    <span v-if="device.asset_code" class="font-mono">{{
                        device.asset_code
                    }}</span>
                    <span v-if="device.location"> · {{ device.location }}</span>
                    <span> · {{ rangeLabel }}</span>
                </p>
            </div>
            <div class="w-full sm:w-72">
                <label
                    class="mb-1 block text-xs font-medium text-muted-foreground"
                    >Device</label
                >
                <select
                    :value="device.id"
                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    @change="onDeviceChange"
                >
                    <option v-for="d in devices" :key="d.id" :value="d.id">
                        {{ d.name ?? d.asset_code ?? `Device #${d.id}` }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Controls -->
        <Card>
            <CardContent class="flex flex-col gap-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-muted-foreground"
                            >From</label
                        >
                        <input
                            v-model="fromInput"
                            type="datetime-local"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-muted-foreground"
                            >To</label
                        >
                        <input
                            v-model="toInput"
                            type="datetime-local"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        />
                    </div>
                    <Button size="sm" @click="applyCustomRange">Apply</Button>

                    <div class="ml-auto flex flex-wrap gap-1.5">
                        <Button
                            v-for="s in shortcuts"
                            :key="s.label"
                            variant="outline"
                            size="sm"
                            @click="applyShortcut(s.hours)"
                        >
                            {{ s.label }}
                        </Button>
                    </div>
                </div>

                <!-- Metric toggle -->
                <label class="inline-flex w-fit items-center gap-2 text-sm">
                    <input
                        v-model="showHumidity"
                        type="checkbox"
                        class="size-4 rounded border-input text-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    />
                    <span class="inline-flex items-center gap-2">
                        <span
                            class="size-2.5 rounded-full"
                            :style="{ backgroundColor: HUMIDITY_COLOR }"
                        />
                        Show humidity
                    </span>
                </label>
            </CardContent>
        </Card>

        <!-- Chart -->
        <Card class="flex-1">
            <CardHeader>
                <CardTitle class="text-base">Temperature trend</CardTitle>
            </CardHeader>
            <CardContent>
                <VendTempChart :series="series" :height="380" />
            </CardContent>
        </Card>

        <!-- Stat cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Card>
                <CardContent class="flex flex-col gap-1">
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <span
                            class="size-2.5 rounded-full"
                            :style="{ backgroundColor: TEMPERATURE_COLOR }"
                        />
                        Temperature
                    </div>
                    <div class="text-2xl font-semibold tabular-nums">
                        {{
                            temperatureStats.last === null
                                ? '—'
                                : `${temperatureStats.last.toFixed(1)}°C`
                        }}
                    </div>
                    <div class="text-xs text-muted-foreground tabular-nums">
                        <template v-if="temperatureStats.count">
                            H {{ temperatureStats.max?.toFixed(1) }}° · L
                            {{ temperatureStats.min?.toFixed(1) }}° ·
                            {{ temperatureStats.count }} pts
                        </template>
                        <template v-else>No data</template>
                    </div>
                </CardContent>
            </Card>

            <Card :class="showHumidity ? '' : 'opacity-50'">
                <CardContent class="flex flex-col gap-1">
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <span
                            class="size-2.5 rounded-full"
                            :style="{ backgroundColor: HUMIDITY_COLOR }"
                        />
                        Humidity
                    </div>
                    <div class="text-2xl font-semibold tabular-nums">
                        {{
                            humidityStats.last === null
                                ? '—'
                                : `${humidityStats.last.toFixed(1)}%`
                        }}
                    </div>
                    <div class="text-xs text-muted-foreground tabular-nums">
                        <template v-if="humidityStats.count">
                            H {{ humidityStats.max?.toFixed(1) }}% · L
                            {{ humidityStats.min?.toFixed(1) }}% ·
                            {{ humidityStats.count }} pts
                        </template>
                        <template v-else>No data</template>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
