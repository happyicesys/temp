<?php

namespace App\Http\Controllers;

use App\Http\Requests\Device\StoreDeviceRequest;
use App\Http\Requests\Device\UpdateDeviceRequest;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Operator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    /**
     * List every device as the post-login landing page. Each row links through
     * to that device's temperature chart, and exposes edit / delete controls.
     */
    public function index(): Response
    {
        $devices = Device::query()
            ->with(['customer:id,name', 'latestVendTemp'])
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'vendor' => $device->vendor,
                'vendor_device_id' => $device->vendor_device_id,
                'asset_code' => $device->asset_code,
                'location' => $device->location,
                'is_active' => $device->is_active,
                'customer' => $device->customer?->name,
                'latest' => $device->latestVendTemp ? [
                    'value' => $device->latestVendTemp->celsius,
                    'recorded_at' => $device->latestVendTemp->recorded_at?->toIso8601String(),
                ] : null,
            ]);

        return Inertia::render('Devices/Index', [
            'devices' => $devices,
        ]);
    }

    /**
     * Show the blank form for registering a device manually.
     */
    public function create(): Response
    {
        return Inertia::render('Devices/Create', [
            'customers' => $this->customerOptions(),
            'operators' => $this->operatorOptions(),
        ]);
    }

    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        Device::query()->create($request->validated());

        return to_route('devices.index')->with('success', 'Device created.');
    }

    /**
     * Show the pre-filled edit form for a single device.
     */
    public function edit(Device $device): Response
    {
        return Inertia::render('Devices/Edit', [
            'device' => [
                'id' => $device->id,
                'vendor' => $device->vendor,
                'vendor_device_id' => $device->vendor_device_id,
                'name' => $device->name,
                'location' => $device->location,
                'model' => $device->model,
                'asset_code' => $device->asset_code,
                'serial_number' => $device->serial_number,
                'customer_id' => $device->customer_id,
                'operator_id' => $device->operator_id,
                'is_active' => $device->is_active,
                'alert_low_temp' => $device->alert_low_temp,
                'alert_high_temp' => $device->alert_high_temp,
                'alert_emails' => $device->alert_emails,
                'alert_phones' => $device->alert_phones,
            ],
            'customers' => $this->customerOptions(),
            'operators' => $this->operatorOptions(),
        ]);
    }

    public function update(UpdateDeviceRequest $request, Device $device): RedirectResponse
    {
        $device->update($request->validated());

        return to_route('devices.index')->with('success', 'Device updated.');
    }

    public function destroy(Device $device): RedirectResponse
    {
        $device->delete();

        return to_route('devices.index')->with('success', 'Device deleted.');
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    private function customerOptions(): Collection
    {
        return Customer::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
            ]);
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    private function operatorOptions(): Collection
    {
        return Operator::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Operator $operator): array => [
                'id' => $operator->id,
                'name' => $operator->name,
            ]);
    }
}
