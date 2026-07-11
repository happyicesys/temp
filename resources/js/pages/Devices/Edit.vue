<script setup lang="ts">
import DeviceForm from '@/components/DeviceForm.vue';
import { Button } from '@/components/ui/button';
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
import { Head, Link, router, useForm } from '@inertiajs/vue3';

interface Option {
    id: number;
    name: string;
}

interface DeviceProps {
    id: number;
    vendor: string;
    vendor_device_id: string;
    name: string;
    location: string | null;
    model: string | null;
    asset_code: string | null;
    serial_number: string | null;
    customer_id: number | null;
    operator_id: number | null;
    is_active: boolean;
    alert_low_temp: number | string | null;
    alert_high_temp: number | string | null;
    alert_emails: string | null;
    alert_phones: string | null;
}

const props = defineProps<{
    device: DeviceProps;
    customers: Option[];
    operators: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Devices', href: '/devices' },
            { title: 'Edit device', href: '#' },
        ],
    },
});

const form = useForm({
    vendor: props.device.vendor,
    vendor_device_id: props.device.vendor_device_id,
    name: props.device.name,
    location: props.device.location,
    model: props.device.model,
    asset_code: props.device.asset_code,
    serial_number: props.device.serial_number,
    customer_id: props.device.customer_id,
    operator_id: props.device.operator_id,
    is_active: props.device.is_active,
    alert_low_temp: props.device.alert_low_temp,
    alert_high_temp: props.device.alert_high_temp,
    alert_emails: props.device.alert_emails,
    alert_phones: props.device.alert_phones,
});

function submit(): void {
    form.put(`/devices/${props.device.id}`);
}

function destroy(): void {
    router.delete(`/devices/${props.device.id}`);
}
</script>

<template>
    <Head :title="`Edit ${device.name}`" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight">Edit device</h1>
            <p class="text-sm text-muted-foreground">
                Update device details, thresholds, and polling status.
            </p>
        </div>

        <form class="space-y-8" @submit.prevent="submit">
            <DeviceForm
                :form="form"
                :customers="customers"
                :operators="operators"
            />

            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing"
                        >Save changes</Button
                    >
                    <Button as-child variant="secondary">
                        <Link href="/devices">Cancel</Link>
                    </Button>
                </div>

                <Dialog>
                    <DialogTrigger as-child>
                        <Button type="button" variant="destructive"
                            >Delete</Button
                        >
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader class="space-y-3">
                            <DialogTitle>Delete this device?</DialogTitle>
                            <DialogDescription>
                                This permanently removes {{ device.name }} and
                                all of its stored readings. This cannot be
                                undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button variant="destructive" @click="destroy"
                                >Delete device</Button
                            >
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </form>
    </div>
</template>
