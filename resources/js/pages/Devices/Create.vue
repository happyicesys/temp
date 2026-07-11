<script setup lang="ts">
import DeviceForm from '@/components/DeviceForm.vue';
import { Button } from '@/components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface Option {
    id: number;
    name: string;
}

const props = defineProps<{
    customers: Option[];
    operators: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Devices', href: '/devices' },
            { title: 'New device', href: '/devices/create' },
        ],
    },
});

const form = useForm({
    vendor: 'jaalee',
    vendor_device_id: '',
    name: '',
    location: '',
    model: '',
    asset_code: '',
    serial_number: '',
    customer_id: props.customers[0]?.id ?? null,
    operator_id: null as number | null,
    is_active: true,
    alert_low_temp: null as number | null,
    alert_high_temp: null as number | null,
    alert_emails: '',
    alert_phones: '',
});

function submit(): void {
    form.post('/devices');
}
</script>

<template>
    <Head title="Register device" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight">
                Register device
            </h1>
            <p class="text-sm text-muted-foreground">
                Add a sensor manually. It starts logging on the next poll.
            </p>
        </div>

        <form class="space-y-8" @submit.prevent="submit">
            <DeviceForm
                :form="form"
                :customers="customers"
                :operators="operators"
            />

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing"
                    >Create device</Button
                >
                <Button as-child variant="secondary">
                    <Link href="/devices">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
