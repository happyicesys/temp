<script setup lang="ts">
import { computed, ref } from 'vue';

interface ChartPoint {
    t: number; // epoch millis
    v: number | null; // celsius, null = gap
}

interface ChartSeries {
    label: string;
    color: string;
    points: ChartPoint[];
}

const props = withDefaults(
    defineProps<{
        series: ChartSeries[];
        height?: number;
    }>(),
    { height: 360 },
);

// Fixed coordinate space; the SVG scales responsively to its container.
const WIDTH = 1000;
const PAD = { top: 16, right: 20, bottom: 36, left: 48 };

const height = computed(() => props.height);
const plotW = computed(() => WIDTH - PAD.left - PAD.right);
const plotH = computed(() => height.value - PAD.top - PAD.bottom);

const allPoints = computed<ChartPoint[]>(() =>
    props.series.flatMap((s) => s.points).filter((p) => p.v !== null),
);

const hasData = computed(() => allPoints.value.length > 0);

const xDomain = computed<[number, number]>(() => {
    const ts = props.series.flatMap((s) => s.points).map((p) => p.t);
    if (ts.length === 0) {
        return [0, 1];
    }
    const min = Math.min(...ts);
    const max = Math.max(...ts);
    return min === max ? [min - 1, max + 1] : [min, max];
});

const yDomain = computed<[number, number]>(() => {
    const vs = allPoints.value.map((p) => p.v as number);
    if (vs.length === 0) {
        return [-25, 10];
    }
    let min = Math.min(...vs);
    let max = Math.max(...vs);
    if (min === max) {
        min -= 1;
        max += 1;
    }
    const margin = (max - min) * 0.1;
    return [min - margin, max + margin];
});

function xScale(t: number): number {
    const [a, b] = xDomain.value;
    return PAD.left + ((t - a) / (b - a)) * plotW.value;
}

function yScale(v: number): number {
    const [a, b] = yDomain.value;
    return PAD.top + (1 - (v - a) / (b - a)) * plotH.value;
}

// Build an SVG path, breaking the line wherever a gap (null) appears.
function pathFor(points: ChartPoint[]): string {
    let d = '';
    let pen = false;
    for (const p of points) {
        if (p.v === null) {
            pen = false;
            continue;
        }
        const cmd = pen ? 'L' : 'M';
        d += `${cmd}${xScale(p.t).toFixed(1)} ${yScale(p.v).toFixed(1)} `;
        pen = true;
    }
    return d.trim();
}

const yTicks = computed<number[]>(() => {
    const [a, b] = yDomain.value;
    const count = 5;
    const step = (b - a) / count;
    return Array.from({ length: count + 1 }, (_, i) => a + step * i);
});

const xTicks = computed<number[]>(() => {
    const [a, b] = xDomain.value;
    const count = 6;
    const step = (b - a) / count;
    return Array.from({ length: count + 1 }, (_, i) => a + step * i);
});

function formatTime(t: number): string {
    return new Date(t).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

// --- Hover interaction -----------------------------------------------------
const hover = ref<{ x: number; t: number; rows: { label: string; color: string; v: number | null }[] } | null>(null);
const svgRef = ref<SVGSVGElement | null>(null);

function onMove(event: MouseEvent): void {
    if (!hasData.value || !svgRef.value) {
        return;
    }
    const rect = svgRef.value.getBoundingClientRect();
    const localX = ((event.clientX - rect.left) / rect.width) * WIDTH;
    const [a, b] = xDomain.value;
    const t = a + ((localX - PAD.left) / plotW.value) * (b - a);

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
        return { label: s.label, color: s.color, v: nearest ? nearest.v : null };
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

const tooltipFlip = computed(() => (hover.value ? hover.value.x > WIDTH * 0.6 : false));
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
            <!-- horizontal gridlines + y labels -->
            <g>
                <template v-for="(tick, i) in yTicks" :key="`y-${i}`">
                    <line
                        :x1="PAD.left"
                        :x2="WIDTH - PAD.right"
                        :y1="yScale(tick)"
                        :y2="yScale(tick)"
                        class="stroke-border"
                        stroke-width="1"
                        stroke-dasharray="2 4"
                    />
                    <text
                        :x="PAD.left - 8"
                        :y="yScale(tick) + 4"
                        text-anchor="end"
                        class="fill-muted-foreground"
                        font-size="11"
                    >
                        {{ tick.toFixed(1) }}°
                    </text>
                </template>
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
            <g fill="none" stroke-width="2" stroke-linejoin="round" stroke-linecap="round">
                <path
                    v-for="(s, i) in series"
                    :key="`line-${i}`"
                    :d="pathFor(s.points)"
                    :stroke="s.color"
                />
            </g>

            <!-- hover crosshair -->
            <g v-if="hover">
                <line
                    :x1="hover.x"
                    :x2="hover.x"
                    :y1="PAD.top"
                    :y2="height - PAD.bottom"
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
                transform: tooltipFlip ? 'translateX(-100%) translateX(-8px)' : 'translateX(8px)',
            }"
        >
            <div class="mb-1 text-xs font-medium text-muted-foreground">
                {{ formatTime(hover.t) }}
            </div>
            <div v-for="(row, i) in hover.rows" :key="`row-${i}`" class="flex items-center gap-2 text-sm">
                <span class="size-2 rounded-full" :style="{ backgroundColor: row.color }" />
                <span class="flex-1">{{ row.label }}</span>
                <span class="font-medium tabular-nums">
                    {{ row.v === null ? '—' : `${row.v.toFixed(1)}°C` }}
                </span>
            </div>
        </div>
    </div>
</template>
