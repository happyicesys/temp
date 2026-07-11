<?php

namespace App\Http\Requests\Device;

use App\Concerns\DeviceValidationRules;
use App\Models\Device;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceRequest extends FormRequest
{
    use DeviceValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $device = $this->route('device');

        return $this->deviceRules($device instanceof Device ? $device->id : null);
    }
}
