<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { InertiaForm } from '@inertiajs/vue3';

interface Option {
    id: number;
    name: string;
}

export interface DeviceFormFields {
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

defineProps<{
    form: InertiaForm<DeviceFormFields>;
    customers: Option[];
    operators: Option[];
}>();

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50';
</script>

<template>
    <div class="grid gap-6 sm:grid-cols-2">
        <div class="grid gap-2 sm:col-span-2">
            <Label for="name">Name</Label>
            <Input
                id="name"
                v-model="form.name"
                required
                placeholder="e.g. Cold Room A"
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="vendor">Vendor</Label>
            <Input
                id="vendor"
                v-model="form.vendor"
                required
                placeholder="jaalee"
            />
            <InputError :message="form.errors.vendor" />
        </div>

        <div class="grid gap-2">
            <Label for="vendor_device_id">Vendor device ID (MAC)</Label>
            <Input
                id="vendor_device_id"
                v-model="form.vendor_device_id"
                required
                placeholder="C60F7D21C31E"
            />
            <InputError :message="form.errors.vendor_device_id" />
        </div>

        <div class="grid gap-2">
            <Label for="customer_id">Customer</Label>
            <select
                id="customer_id"
                v-model.number="form.customer_id"
                :class="selectClass"
                required
            >
                <option :value="null" disabled>Select a customer…</option>
                <option
                    v-for="customer in customers"
                    :key="customer.id"
                    :value="customer.id"
                >
                    {{ customer.name }}
                </option>
            </select>
            <InputError :message="form.errors.customer_id" />
        </div>

        <div class="grid gap-2">
            <Label for="operator_id">Operator (optional)</Label>
            <select
                id="operator_id"
                v-model.number="form.operator_id"
                :class="selectClass"
            >
                <option :value="null">None</option>
                <option
                    v-for="operator in operators"
                    :key="operator.id"
                    :value="operator.id"
                >
                    {{ operator.name }}
                </option>
            </select>
            <InputError :message="form.errors.operator_id" />
        </div>

        <div class="grid gap-2">
            <Label for="location">Location</Label>
            <Input
                id="location"
                v-model="form.location"
                placeholder="e.g. Warehouse 3"
            />
            <InputError :message="form.errors.location" />
        </div>

        <div class="grid gap-2">
            <Label for="model">Model</Label>
            <Input id="model" v-model="form.model" placeholder="e.g. F51C" />
            <InputError :message="form.errors.model" />
        </div>

        <div class="grid gap-2">
            <Label for="asset_code">Asset code</Label>
            <Input id="asset_code" v-model="form.asset_code" />
            <InputError :message="form.errors.asset_code" />
        </div>

        <div class="grid gap-2">
            <Label for="serial_number">Serial number</Label>
            <Input id="serial_number" v-model="form.serial_number" />
            <InputError :message="form.errors.serial_number" />
        </div>

        <div class="grid gap-2">
            <Label for="alert_low_temp">Alert low temp (°C)</Label>
            <Input
                id="alert_low_temp"
                type="number"
                step="0.01"
                v-model="form.alert_low_temp"
                placeholder="-25"
            />
            <InputError :message="form.errors.alert_low_temp" />
        </div>

        <div class="grid gap-2">
            <Label for="alert_high_temp">Alert high temp (°C)</Label>
            <Input
                id="alert_high_temp"
                type="number"
                step="0.01"
                v-model="form.alert_high_temp"
                placeholder="-15"
            />
            <InputError :message="form.errors.alert_high_temp" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label for="alert_emails">Alert emails</Label>
            <Input
                id="alert_emails"
                v-model="form.alert_emails"
                placeholder="ops@example.com, lead@example.com"
            />
            <p class="text-xs text-muted-foreground">Comma-separated list.</p>
            <InputError :message="form.errors.alert_emails" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label for="alert_phones">Alert phones</Label>
            <Input
                id="alert_phones"
                v-model="form.alert_phones"
                placeholder="+6591234567, +6598765432"
            />
            <p class="text-xs text-muted-foreground">Comma-separated list.</p>
            <InputError :message="form.errors.alert_phones" />
        </div>

        <div class="flex items-center gap-2 sm:col-span-2">
            <Checkbox id="is_active" v-model="form.is_active" />
            <Label for="is_active"
                >Active (included in the polling schedule)</Label
            >
            <InputError :message="form.errors.is_active" />
        </div>
    </div>
</template>
