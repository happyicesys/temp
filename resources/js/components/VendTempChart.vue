<script setup lang="ts">
import { computed, ref } from 'vue';
import { DEFAULT_TIME_ZONE, formatEpochInZone } from '@/lib/timezone';
import type { TimeZoneId } from '@/lib/timezone';

interface ChartPoint {
    t: number; // epoch millis
    v: number | null; // measured value, null = gap
}

interface ChartSeries {
    label: string;
    color: string;
    unit: string; // e.g. '°C' or '%'
    axis: 'left' | 'right';
    points: ChartPoint[];
}

const props = withDefaults(
    defineProps<{
        series: ChartSeries[];
        height?: number;
        timeZone?: TimeZoneId;
    }>(),
    { height: 360, timeZone: DEFAULT_TIME_ZONE },
);

// Fixed coordinate space; the SVG scales responsively to its container.
const WIDTH = 1000;

const leftSeries = computed(() =>
    props.series.filter((s) => s.axis === 'left'),
);
const rightSeries = computed(() =>
    props.series.filter((s) => s.axis === 'right'),
);
const hasRightAxis = computed(() => rightSeries.value.length > 0);

// Right padding only needs to grow when a second axis is on screen.
const pad = computed(() => ({
    top: 16,
    right: hasRightAxis.value ? 48 : 20,
    bottom: 36,
    left: 48,
}));

const height = computed(() => props.height);
const plotW = computed(() => WIDTH - pad.value.left - pad.value.right);
const plotH = computed(() => height.value - pad.value.top - pad.value.bottom);

const leftUnit = computed(() => leftSeries.value[0]?.unit ?? '°C');
const rightUnit = computed(() => rightSeries.value[0]?.unit ?? '%');

function valuesFor(list: ChartSeries[]): number[] {
    return list
        .flatMap((s) => s.points)
        .filter((p) => p.v !== null)
        .map((p) => p.v as number);
}

const hasData = computed(() => valuesFor(props.series).length > 0);

const xDomain = computed<[number, number]>(() => {
    const ts = props.series.flatMap((s) => s.points).map((p) => p.t);

    if (ts.length === 0) {
        return [0, 1];
    }

    const min = Math.min(...ts);
    const max = Math.max(...ts);

    return min === max ? [min - 1, max + 1] : [min, max];
});

function domainFor(
    list: ChartSeries[],
    fallback: [number, number],
): [number, number] {
    const vs = valuesFor(list);

    if (vs.length === 0) {
        return fallback;
    }

    let min = Math.min(...vs);
    let max = Math.max(...vs);

    if (min === max) {
        min -= 1;
        max += 1;
    }

    const margin = (max - min) * 0.1;

    return [min - margin, max + margin];
}

const leftDomain = computed<[number, number]>(() =>
    domainFor(leftSeries.value, [-25, 10]),
);
const rightDomain = computed<[number, number]>(() =>
    domainFor(rightSeries.value, [0, 100]),
);

function xScale(t: number): number {
    const [a, b] = xDomain.value;

    return pad.value.left + ((t - a) / (b - a)) * plotW.value;
}

function yScaleFor(axis: 'left' | 'right', v: number): number {
    const [a, b] = axis === 'left' ? leftDomain.value : rightDomain.value;

    return pad.value.top + (1 - (v - a) / (b - a)) * plotH.value;
}

// Build an SVG path, breaking the line wherever a gap (null) appears.
function pathFor(s: ChartSeries): string {
    let d = '';
    let pen = false;

    for (const p of s.points) {
        if (p.v === null) {
            pen = false;
            continue;
        }

        const cmd = pen ? 'L' : 'M';
        d += `${cmd}${xScale(p.t).toFixed(1)} ${yScaleFor(s.axis, p.v).toFixed(1)} `;
        pen = true;
    }

    return d.trim();
}

function ticksFor(domain: [number, number]): number[] {
    const [a, b] = domain;
    const count = 5;
    const step = (b - a) / count;

    return Array.from({ length: count + 1 }, (_, i) => a + step * i);
}

const leftTicks = computed(() => ticksFor(leftDomain.value));
const rightTicks = computed(() => ticksFor(rightDomain.value));

const xTicks = computed<number[]>(() => {
    const [a, b] = xDomain.value;
    const count = 6;
    const step = (b - a) / count;

    return Array.from({ length: count + 1 }, (_, i) => a + step * i);
});

function formatTime(t: number): string {
    return formatEpochInZone(t, props.timeZone);
}

function formatValue(v: number | null, unit: string): string {
    return v === null ? '—' : `${v.toFixed(1)}${unit}`;
}

// --- Hover interaction -----------------------------------------------------
const hover = ref<{
    x: number;
    t: number;
    rows: { label: string; color: string; text: string }[];
} | null>(null);
const svgRef = ref<SVGSVGElement | null>(null);

function onMove(event: MouseEvent): void {
    if (!hasData.value || !svgRef.value) {
        return;
    }

    const rect = svgRef.value.getBoundingClientRect();
    const localX = ((event.clientX - rect.left) / rect.width) * WIDTH;
    const [a, b] = xDomain.value;
    const t = a + ((localX - pad.value.left) / plotW.value) * (b - a);

    const rows = props.series.map((s) => {
        let nearest: ChartPoint | null = null;
        let best = Infinity;

        for (const p of s.points) {
            if (p.v === null) {
                continue;
            }

            const dist = Math.abs(p.t - t);

            if (dist < best) {
                best = dist;
                nearest = p;
            }
        }

        return {
            label: s.label,
            color: s.color,
            text: formatValue(nearest ? nearest.v : null, s.unit),
        };
    });

    const clampedT = Math.max(a, Math.min(b, t));
    hover.value = { x: xScale(clampedT), t: clampedT, rows };
}

function onLeave(): void {
    hover.value = null;
}

const tooltipLeft = computed(() => {
    if (!hover.value) {
        return '0%';
    }

    return `${(hover.value.x / WIDTH) * 100}%`;
});

const tooltipFlip = computed(() =>
    hover.value ? hover.value.x > WIDTH * 0.6 : false,
);
</script>

<template>
    <div class="relative w-full">
        <svg
            ref="svgRef"
            :viewBox="`0 0 ${WIDTH} ${height}`"
            class="w-full"
            :style="{ height: `${height}px` }"
            preserveAspectRatio="none"
            @mousemove="onMove"
            @mouseleave="onLeave"
        >
            <!-- horizontal gridlines + left (temperature) axis labels -->
            <g>
                <template v-for="(tick, i) in leftTicks" :key="`y-${i}`">
                    <line
                        :x1="pad.left"
                        :x2="WIDTH - pad.right"
                        :y1="yScaleFor('left', tick)"
                        :y2="yScaleFor('left', tick)"
                        class="stroke-border"
                        stroke-width="1"
                        stroke-dasharray="2 4"
                    />
                    <text
                        :x="pad.left - 8"
                        :y="yScaleFor('left', tick) + 4"
                        text-anchor="end"
                        class="fill-muted-foreground"
                        font-size="11"
                    >
                        {{ tick.toFixed(1) }}{{ leftUnit }}
                    </text>
                </template>
            </g>

            <!-- right (humidity) axis labels -->
            <g v-if="hasRightAxis">
                <text
                    v-for="(tick, i) in rightTicks"
                    :key="`ry-${i}`"
                    :x="WIDTH - pad.right + 8"
                    :y="yScaleFor('right', tick) + 4"
                    text-anchor="start"
                    class="fill-muted-foreground"
                    font-size="11"
                >
                    {{ tick.toFixed(0) }}{{ rightUnit }}
                </text>
            </g>

            <!-- x labels -->
            <g>
                <text
                    v-for="(tick, i) in xTicks"
                    :key="`x-${i}`"
                    :x="xScale(tick)"
                    :y="height - 12"
                    text-anchor="middle"
                    class="fill-muted-foreground"
                    font-size="11"
                >
                    {{ formatTime(tick) }}
                </text>
            </g>

            <!-- series lines -->
            <g
                fill="none"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
            >
                <path
                    v-for="(s, i) in series"
                    :key="`line-${i}`"
                    :d="pathFor(s)"
                    :stroke="s.color"
                    :stroke-dasharray="s.axis === 'right' ? '5 3' : undefined"
                />
            </g>

            <!-- hover crosshair -->
            <g v-if="hover">
                <line
                    :x1="hover.x"
                    :x2="hover.x"
                    :y1="pad.top"
                    :y2="height - pad.bottom"
                    class="stroke-foreground/40"
                    stroke-width="1"
                />
            </g>

            <!-- empty state -->
            <text
                v-if="!hasData"
                :x="WIDTH / 2"
                :y="height / 2"
                text-anchor="middle"
                class="fill-muted-foreground"
                font-size="14"
            >
                No readings in this range
            </text>
        </svg>

        <!-- tooltip -->
        <div
            v-if="hover && hasData"
            class="pointer-events-none absolute top-2 z-10 min-w-40 rounded-lg border border-border bg-popover/95 p-2 text-popover-foreground shadow-md backdrop-blur"
            :style="{
                left: tooltipLeft,
                transform: tooltipFlip
                    ? 'translateX(-100%) translateX(-8px)'
                    : 'translateX(8px)',
            }"
        >
            <div class="mb-1 text-xs font-medium text-muted-foreground">
                {{ formatTime(hover.t) }}
            </div>
            <div
                v-for="(row, i) in hover.rows"
                :key="`row-${i}`"
                class="flex items-center gap-2 text-sm"
            >
                <span
                    class="size-2 rounded-full"
                    :style="{ backgroundColor: row.color }"
                />
                <span class="flex-1">{{ row.label }}</span>
                <span class="font-medium tabular-nums">{{ row.text }}</span>
            </div>
        </div>
    </div>
</template>
