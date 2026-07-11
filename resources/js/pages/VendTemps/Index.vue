<script setup lang="ts">
import VendTempChart from '@/components/VendTempChart.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Reading {
    type: number;
    value: number | null;
    is_keep: boolean;
    recorded_at: string | null;
}

interface DeviceSummary {
    id: number;
    name: string | null;
    asset_code: string | null;
}

const props = defineProps<{
    device: { id: number; name: string | null; asset_code: string | null; location: string | null };
    devices: DeviceSummary[];
    readings: Reading[];
    filters: { datetime_from: string; datetime_to: string };
    selectedTypes: number[];
    typeLabels: Record<number, string>;
    alerts: Record<string, { desc: string; value: number; is_triggered: boolean }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Temperature', href: '#' }],
    },
});

// A deliberately distinct palette from the source project's chart styling.
const TYPE_COLORS: Record<number, string> = {
    1: '#0ea5e9', // T1 chamber — sky
    2: '#f59e0b', // T2 evaporator — amber
    3: '#8b5cf6', // T3 — violet
    4: '#ec4899', // T4 — pink
};

const selectedTypes = ref<number[]>([...props.selectedTypes]);

const typeEntries = computed(() =>
    Object.entries(props.typeLabels).map(([key, label]) => ({
        type: Number(key),
        label,
        color: TYPE_COLORS[Number(key)] ?? '#64748b',
    })),
);

const series = computed(() =>
    selectedTypes.value
        .slice()
        .sort((a, b) => a - b)
        .map((type) => ({
            label: props.typeLabels[type] ?? `T${type}`,
            color: TYPE_COLORS[type] ?? '#64748b',
            points: props.readings
                .filter((r) => r.type === type && r.recorded_at !== null)
                .map((r) => ({ t: Date.parse(r.recorded_at as string), v: r.value }))
                .sort((a, b) => a.t - b.t),
        }))
        .filter((s) => s.points.length > 0),
);

// Per-type summary stats for the legend cards.
const stats = computed(() =>
    typeEntries.value.map((entry) => {
        const values = props.readings
            .filter((r) => r.type === entry.type && r.value !== null)
            .map((r) => r.value as number);
        return {
            ...entry,
            active: selectedTypes.value.includes(entry.type),
            count: values.length,
            min: values.length ? Math.min(...values) : null,
            max: values.length ? Math.max(...values) : null,
            last: values.length ? values[values.length - 1] : null,
        };
    }),
);

const rangeLabel = computed(
    () => `${formatLabel(props.filters.datetime_from)} → ${formatLabel(props.filters.datetime_to)}`,
);

function formatLabel(iso: string): string {
    const d = new Date(iso);
    return Number.isNaN(d.getTime())
        ? iso
        : d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
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

type Query = Record<string, string | number | number[]>;

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
        types: selectedTypes.value,
    };
}

function toggleType(type: number): void {
    const next = selectedTypes.value.includes(type)
        ? selectedTypes.value.filter((t) => t !== type)
        : [...selectedTypes.value, type];
    if (next.length === 0) {
        return; // keep at least one series on screen
    }
    selectedTypes.value = next;
    navigate(currentQuery());
}

const shortcuts: { label: string; hours: number }[] = [
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
        types: selectedTypes.value,
    });
}

function applyCustomRange(): void {
    navigate(currentQuery());
}

function onDeviceChange(event: Event): void {
    const id = (event.target as HTMLSelectElement).value;
    router.get(`/devices/${id}/vend-temps`, { types: selectedTypes.value }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Temperature" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <!-- Header -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ device.name ?? 'Device' }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    <span v-if="device.asset_code" class="font-mono">{{ device.asset_code }}</span>
                    <span v-if="device.location"> · {{ device.location }}</span>
                    <span> · {{ rangeLabel }}</span>
                </p>
            </div>
            <div class="w-full sm:w-72">
                <label class="mb-1 block text-xs font-medium text-muted-foreground">Device</label>
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
                        <label class="text-xs font-medium text-muted-foreground">From</label>
                        <input
                            v-model="fromInput"
                            type="datetime-local"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-muted-foreground">To</label>
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

                <!-- Type toggles -->
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="entry in typeEntries"
                        :key="entry.type"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm transition-colors"
                        :class="
                            selectedTypes.includes(entry.type)
                                ? 'border-transparent bg-secondary text-secondary-foreground'
                                : 'border-border text-muted-foreground hover:bg-accent'
                        "
                        @click="toggleType(entry.type)"
                    >
                        <span class="size-2.5 rounded-full" :style="{ backgroundColor: entry.color }" />
                        {{ entry.label }}
                    </button>
                </div>
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
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <Card v-for="stat in stats" :key="stat.type" :class="stat.active ? '' : 'opacity-50'">
                <CardContent class="flex flex-col gap-1">
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <span class="size-2.5 rounded-full" :style="{ backgroundColor: stat.color }" />
                        {{ stat.label }}
                    </div>
                    <div class="text-2xl font-semibold tabular-nums">
                        {{ stat.last === null ? '—' : `${stat.last.toFixed(1)}°C` }}
                    </div>
                    <div class="text-xs text-muted-foreground tabular-nums">
                        <template v-if="stat.count">
                            H {{ stat.max?.toFixed(1) }}° · L {{ stat.min?.toFixed(1) }}° · {{ stat.count }} pts
                        </template>
                        <template v-else>No data</template>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Variance thresholds -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Variance thresholds</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-2">
                <span
                    v-for="(alert, key) in alerts"
                    :key="key"
                    class="rounded-md border border-border bg-muted/40 px-3 py-1 text-sm text-muted-foreground"
                >
                    {{ alert.desc }}
                </span>
            </CardContent>
        </Card>
    </div>
</template>
