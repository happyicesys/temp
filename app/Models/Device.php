<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'vendor',
    'vendor_device_id',
    'serial_number',
    'asset_code',
    'model',
    'name',
    'location',
    'customer_id',
    'operator_id',
    'is_active',
    'alert_low_temp',
    'alert_high_temp',
    'alert_emails',
    'alert_phones',
    'last_polled_at',
    'last_reading_at',
    'last_temperature',
    'last_humidity',
    'is_online',
    'went_offline_at',
])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'alert_low_temp' => 'decimal:2',
            'alert_high_temp' => 'decimal:2',
            'last_polled_at' => 'datetime',
            'last_reading_at' => 'datetime',
            'last_temperature' => 'decimal:2',
            'last_humidity' => 'decimal:2',
            'is_online' => 'boolean',
            'went_offline_at' => 'datetime',
        ];
    }

    /**
     * Whether the device has reported a reading recently enough to count as
     * online. Derived live from the reading cache so the badge is never stale;
     * the scheduled status check persists the same result onto {@see $is_online}
     * for offline-transition detection.
     *
     * @param  int|null  $thresholdSeconds  Overrides the configured window.
     */
    public function hasFreshReading(?int $thresholdSeconds = null): bool
    {
        if ($this->last_reading_at === null) {
            return false;
        }

        $thresholdSeconds ??= (int) config('sensors.offline_after_seconds', 600);

        return $this->last_reading_at->greaterThanOrEqualTo(now()->subSeconds($thresholdSeconds));
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Operator, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /**
     * All temperature samples this device has reported.
     *
     * @return HasMany<Temp, $this>
     */
    public function temps(): HasMany
    {
        return $this->hasMany(Temp::class);
    }

    /**
     * Convenience accessor for charts / dashboards.
     *
     * @return HasOne<Temp, $this>
     */
    public function latestTemp(): HasOne
    {
        return $this->hasOne(Temp::class)->latestOfMany('recorded_at');
    }

    /**
     * Parse the comma-separated alert_emails column into a clean list.
     *
     * @return array<int, string>
     */
    public function alertEmailList(): array
    {
        return $this->splitContactList($this->alert_emails);
    }

    /**
     * Parse the comma-separated alert_phones column into a clean list.
     *
     * @return array<int, string>
     */
    public function alertPhoneList(): array
    {
        return $this->splitContactList($this->alert_phones);
    }

    /**
     * @return array<int, string>
     */
    protected function splitContactList(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->values()
            ->all();
    }
}
