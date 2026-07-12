<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ChevronRight,
    Droplets,
    MapPin,
    Pencil,
    Plus,
    Thermometer,
    Trash2,
} from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';

interface DeviceRow {
    id: number;
    name: string | null;
    vendor: string;
    vendor_device_id: string;
    asset_code: string | null;
    location: string | null;
    is_active: boolean;
    is_online: boolean;
    customer: string | null;
    latest: {
        temperature: number | null;
        humidity: number | null;
        recorded_at: string | null;
    } | null;
}

const props = defineProps<{
    devices: DeviceRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Devices', href: '/devices' }],
    },
});

const page = usePage();
const hasDevices = computed(() => props.devices.length > 0);

watch(
    () => (page.props.flash as { success?: string } | undefined)?.success,
    (message) => {
        if (message) {
            toast.success(message);
        }
    },
    { immediate: true },
);

function formatTime(iso: string | null): string {
    if (!iso) {
        return 'No readings yet';
    }
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

/** Compact "time ago" for the last-seen line, e.g. "4h ago", "just now". */
function timeAgo(iso: string | null): string {
    if (!iso) {
        return 'never';
    }
    const then = new Date(iso).getTime();
    if (Number.isNaN(then)) {
        return iso;
    }
    const seconds = Math.max(0, Math.round((Date.now() - then) / 1000));
    if (seconds < 60) {
        return 'just now';
    }
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) {
        return `${minutes}m ago`;
    }
    const hours = Math.round(minutes / 60);
    if (hours < 24) {
        return `${hours}h ago`;
    }
    return `${Math.round(hours / 24)}d ago`;
}

type StatusKey = 'online' | 'offline' | 'paused';

interface DeviceStatus {
    key: StatusKey;
    label: string;
    badgeClass: string;
    dotClass: string;
    pulse: boolean;
}

/** Resolve the display status: paused (manually disabled) wins, else live online/offline. */
function statusOf(device: DeviceRow): DeviceStatus {
    if (!device.is_active) {
        return {
            key: 'paused',
            label: 'Paused',
            badgeClass:
                'bg-muted text-muted-foreground ring-1 ring-inset ring-border',
            dotClass: 'bg-muted-foreground/60',
            pulse: false,
        };
    }
    if (device.is_online) {
        return {
            key: 'online',
            label: 'Online',
            badgeClass:
                'bg-emerald-500/10 text-emerald-600 ring-1 ring-inset ring-emerald-500/20 dark:text-emerald-400',
            dotClass: 'bg-emerald-500',
            pulse: true,
        };
    }
    return {
        key: 'offline',
        label: 'Offline',
        badgeClass:
            'bg-rose-500/10 text-rose-600 ring-1 ring-inset ring-rose-500/20 dark:text-rose-400',
        dotClass: 'bg-rose-500',
        pulse: false,
    };
}

function destroy(id: number): void {
    router.delete(`/devices/${id}`);
}
</script>

<template>
    <Head title="Devices" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Devices</h1>
                <p class="text-sm text-muted-foreground">
                    Manage sensors and view their temperature history.
                </p>
            </div>
            <Button as-child>
                <Link href="/devices/create">
                    <Plus class="size-4" />
                    Add device
                </Link>
            </Button>
        </div>

        <div v-if="hasDevices" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="device in devices" :key="device.id" class="h-full">
                <CardContent class="flex flex-col gap-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="truncate font-medium">
                                {{ device.name ?? `Device #${device.id}` }}
                            </div>
                            <div
                                class="truncate font-mono text-xs text-muted-foreground"
                            >
                                {{
                                    device.asset_code ?? device.vendor_device_id
                                }}
                            </div>
                        </div>
                        <span
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="statusOf(device).badgeClass"
                        >
                            <span class="relative flex size-2">
                                <span
                                    v-if="statusOf(device).pulse"
                                    class="absolute inline-flex size-full animate-ping rounded-full opacity-75"
                                    :class="statusOf(device).dotClass"
                                />
                                <span
                                    class="relative inline-flex size-2 rounded-full"
                                    :class="statusOf(device).dotClass"
                                />
                            </span>
                            {{ statusOf(device).label }}
                        </span>
                    </div>

                    <div class="flex items-center gap-4">
                        <div
                            class="flex items-center gap-2 text-2xl font-semibold tabular-nums"
                        >
                            <Thermometer
                                class="size-5 text-muted-foreground"
                            />
                            {{
                                device.latest &&
                                device.latest.temperature !== null
                                    ? `${device.latest.temperature.toFixed(1)}°C`
                                    : '—'
                            }}
                        </div>
                        <div
                            class="flex items-center gap-1.5 text-sm font-medium tabular-nums text-muted-foreground"
                        >
                            <Droplets class="size-4" />
                            {{
                                device.latest &&
                                device.latest.humidity !== null
                                    ? `${device.latest.humidity.toFixed(1)}%`
                                    : '—'
                            }}
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between text-xs text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1 truncate">
                            <MapPin class="size-3.5 shrink-0" />
                            {{
                                device.location ??
                                device.customer ??
                                'Unknown location'
                            }}
                        </span>
                        <span
                            class="shrink-0 tabular-nums"
                            :class="
                                statusOf(device).key === 'offline'
                                    ? 'font-medium text-rose-600 dark:text-rose-400'
                                    : ''
                            "
                            :title="formatTime(device.latest?.recorded_at ?? null)"
                        >
                            <template v-if="device.latest?.recorded_at">
                                {{ statusOf(device).key === 'offline' ? 'Last seen ' : '' }}{{ timeAgo(device.latest.recorded_at) }}
                            </template>
                            <template v-else>No readings yet</template>
                        </span>
                    </div>

                    <div
                        class="mt-1 flex items-center justify-between border-t pt-3"
                    >
                        <Link
                            :href="`/devices/${device.id}/vend-temps`"
                            class="inline-flex items-center gap-1 text-sm font-medium text-primary outline-none hover:underline focus-visible:underline"
                        >
                            View temperatures
                            <ChevronRight class="size-4" />
                        </Link>

                        <div class="flex items-center gap-1">
                            <Button
                                as-child
                                variant="ghost"
                                size="icon"
                                aria-label="Edit device"
                            >
                                <Link :href="`/devices/${device.id}/edit`">
                                    <Pencil class="size-4" />
                                </Link>
                            </Button>

                            <Dialog>
                                <DialogTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Delete device"
                                    >
                                        <Trash2
                                            class="size-4 text-destructive"
                                        />
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader class="space-y-3">
                                        <DialogTitle
                                            >Delete this device?</DialogTitle
                                        >
                                        <DialogDescription>
                                            This permanently removes
                                            {{
                                                device.name ??
                                                `Device #${device.id}`
                                            }}
                                            and all of its stored readings. This
                                            cannot be undone.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button variant="secondary"
                                                >Cancel</Button
                                            >
                                        </DialogClose>
                                        <Button
                                            variant="destructive"
                                            @click="destroy(device.id)"
                                            >Delete device</Button
                                        >
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card v-else>
            <CardContent
                class="flex flex-col items-center gap-2 py-12 text-center"
            >
                <Thermometer class="size-8 text-muted-foreground" />
                <p class="font-medium">No devices yet</p>
                <p class="text-sm text-muted-foreground">
                    Add one manually, or let the poll auto-register your Jaalee
                    sensors on the next tick.
                </p>
                <Button as-child class="mt-2">
                    <Link href="/devices/create">
                        <Plus class="size-4" />
                        Add device
                    </Link>
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
