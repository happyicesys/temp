<?php

namespace App\Http\Resources;

use App\Models\VendTemp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VendTemp
 */
class VendTempResource extends JsonResource
{
    /**
     * Transform the reading for the frontend chart.
     *
     * Exposes the celsius value (null when the probe errored) rather than the
     * raw scaled integer, so the Vue layer never deals with the x10 encoding.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'value' => $this->celsius,
            'is_keep' => $this->is_keep,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
        ];
    }
}
