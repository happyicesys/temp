<?php

namespace App\Models;

use Database\Factories\VendTempFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single temperature sample reported by a refrigeration unit.
 *
 * Ported from the mark1 vending-machine project and adapted to this codebase:
 * readings now hang off {@see Device} (rather than a vending "Vend") and use a
 * device-reported `recorded_at` timestamp to match {@see Temp}.
 *
 * Values are stored as integers scaled by ten (e.g. `135` == `13.5°C`) to keep
 * the column compact and index-friendly; use {@see VendTemp::celsius()} to read
 * the human-facing figure.
 */
#[Fillable([
    'device_id',
    'value',
    'is_keep',
    'type',
    'recorded_at',
])]
class VendTemp extends Model
{
    /** @use HasFactory<VendTempFactory> */
    use HasFactory;

    /**
     * Variance thresholds between the chamber (T1) and evaporator (T2) probes.
     * Mirrors the alerting tiers used by the source project.
     */
    const DEFAULT_ALERTS = [
        'TEMP_TYPE_VARIANCE_TIER_ONE' => [
            'desc' => 'T1-T2 >= 5°C',
            'value' => 5,
            'is_triggered' => false,
        ],
        'TEMP_TYPE_VARIANCE_TIER_TWO' => [
            'desc' => 'T1-T2 >= 10°C',
            'value' => 10,
            'is_triggered' => false,
        ],
    ];

    /** Sentinel reported by the hardware when a probe reading is invalid. */
    const TEMPERATURE_ERROR = 32767;

    /** Scaling factor applied to the raw integer `value` to obtain celsius. */
    const VALUE_DIVISOR = 10;

    const TYPE_CHAMBER = 1;
    const TYPE_EVAPORATOR = 2;
    const TYPE_THREE = 3;
    const TYPE_FOUR = 4;

    /**
     * Human-facing labels for each probe type.
     *
     * @return array<int, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_CHAMBER => 'T1: Chamber',
            self::TYPE_EVAPORATOR => 'T2: Evaporator',
            self::TYPE_THREE => 'T3',
            self::TYPE_FOUR => 'T4',
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'is_keep' => 'boolean',
            'type' => 'integer',
            'recorded_at' => 'datetime',
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
     * The reading converted to celsius, or null when the probe reported the
     * error sentinel.
     *
     * @return Attribute<float|null, never>
     */
    protected function celsius(): Attribute
    {
        return Attribute::make(
            get: fn (): ?float => $this->value === self::TEMPERATURE_ERROR
                ? null
                : round($this->value / self::VALUE_DIVISOR, 1),
        );
    }

    /**
     * Scope a query to readings within a given time range.
     *
     * Uses the (device_id, recorded_at) composite index when combined with
     * `where('device_id', ...)`, keeping the chart query cheap as the table
     * grows.
     *
     * @param  Builder<VendTemp>  $query
     * @return Builder<VendTemp>
     */
    public function scopeBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->whereBetween('recorded_at', [$from, $to]);
    }

    /**
     * Scope a query to one or more probe types.
     *
     * @param  Builder<VendTemp>  $query
     * @param  array<int, int>  $types
     * @return Builder<VendTemp>
     */
    public function scopeOfTypes(Builder $query, array $types): Builder
    {
        return $query->whereIn('type', $types);
    }
}
