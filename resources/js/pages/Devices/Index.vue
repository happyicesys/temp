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
                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="
                                device.is_active
                                    ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ device.is_active ? 'Active' : 'Inactive' }}
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
                        <span class="shrink-0">{{
                            formatTime(device.latest?.recorded_at ?? null)
                        }}</span>
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
