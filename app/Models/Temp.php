<?php

namespace App\Models;

use Database\Factories\TempFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'device_id',
    'temperature',
    'humidity',
    'pressure',
    'battery_level',
    'is_online',
    'recorded_at',
    'raw_payload',
])]
class Temp extends Model
{
    /** @use HasFactory<TempFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temperature' => 'decimal:2',
            'humidity' => 'decimal:2',
            'pressure' => 'decimal:2',
            'battery_level' => 'integer',
            'is_online' => 'boolean',
            'recorded_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Scope a query to readings within a given time range.
     *
     * Uses the (device_id, recorded_at) composite index when combined with
     * `where('device_id', ...)`, so the chart query stays cheap as the table
     * grows past a few million rows.
     *
     * @param  Builder<Temp>  $query
     */
    public function scopeBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->whereBetween('recorded_at', [$from, $to]);
    }
}
