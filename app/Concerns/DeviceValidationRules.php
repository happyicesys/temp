<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for creating and updating a Device.
 *
 * Kept in one place so the Store and Update requests stay in lock-step; the
 * only difference between them is which device id (if any) the uniqueness
 * check must ignore.
 */
trait DeviceValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function deviceRules(?int $ignoreDeviceId = null): array
    {
        $vendor = (string) $this->input('vendor');

        return [
            'vendor' => ['required', 'string', 'max:255'],
            // A vendor_device_id is unique only within its vendor namespace,
            // matching the (vendor, vendor_device_id) unique index on the table.
            'vendor_device_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('devices', 'vendor_device_id')
                    ->where(fn ($query) => $query->where('vendor', $vendor))
                    ->ignore($ignoreDeviceId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'asset_code' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'operator_id' => ['nullable', 'integer', 'exists:operators,id'],
            'is_active' => ['boolean'],
            'alert_low_temp' => ['nullable', 'numeric', 'between:-100,100'],
            'alert_high_temp' => ['nullable', 'numeric', 'between:-100,100'],
            'alert_emails' => ['nullable', 'string', 'max:1000'],
            'alert_phones' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
