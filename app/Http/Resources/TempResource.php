<?php

namespace App\Http\Resources;

use App\Models\Temp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Temp
 */
class TempResource extends JsonResource
{
    /**
     * Transform a reading for the frontend chart.
     *
     * Exposes the metrics this logger reports — temperature (°C) and humidity
     * (%) — alongside the device-reported sample time. Values are already cast
     * to decimals on the model, so the Vue layer receives plain numbers.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'temperature' => $this->temperature === null ? null : (float) $this->temperature,
            'humidity' => $this->humidity === null ? null : (float) $this->humidity,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
        ];
    }
}
