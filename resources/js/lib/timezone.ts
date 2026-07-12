/**
 * Timezone helpers for the temperature views.
 *
 * Readings are stored and transmitted in UTC. These helpers let the UI display
 * and accept times in a user-chosen IANA zone without any server-side change,
 * using only the built-in `Intl` APIs (no external date library).
 */

/** IANA identifier for a supported display zone. */
export type TimeZoneId = 'Asia/Singapore' | 'Asia/Jakarta' | 'UTC';

/** A selectable zone plus its human label. */
export interface TimeZoneOption {
    id: TimeZoneId;
    label: string;
}

/** Zones offered in the dropdown, in display order. */
export const TIME_ZONE_OPTIONS: readonly TimeZoneOption[] = [
    { id: 'Asia/Singapore', label: 'Singapore (UTC+8)' },
    { id: 'Asia/Jakarta', label: 'Jakarta (UTC+7)' },
    { id: 'UTC', label: 'UTC (server time)' },
] as const;

/** Zone used before the user makes a choice. */
export const DEFAULT_TIME_ZONE: TimeZoneId = 'Asia/Singapore';

/** localStorage key under which the chosen zone is remembered. */
export const TIME_ZONE_STORAGE_KEY = 'vend-temps.timezone';

/**
 * Narrow an arbitrary string to a supported {@link TimeZoneId}, falling back to
 * {@link DEFAULT_TIME_ZONE} when it is unknown (e.g. stale localStorage value).
 */
export function normalizeTimeZone(
    value: string | null | undefined,
): TimeZoneId {
    return TIME_ZONE_OPTIONS.some((option) => option.id === value)
        ? (value as TimeZoneId)
        : DEFAULT_TIME_ZONE;
}

/**
 * Format a UTC ISO timestamp for display in the given zone.
 *
 * @param iso      A UTC ISO-8601 string, or null/invalid (returns a dash).
 * @param timeZone The IANA zone to render in.
 */
export function formatInZone(iso: string | null, timeZone: TimeZoneId): string {
    if (iso === null) {
        return '—';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    return formatEpochInZone(date.getTime(), timeZone);
}

/**
 * Format an epoch-millis instant for display in the given zone. Shared by chart
 * axis/tooltip labels which work in numeric time.
 */
export function formatEpochInZone(
    epochMs: number,
    timeZone: TimeZoneId,
): string {
    return new Date(epochMs).toLocaleString(undefined, {
        timeZone,
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Offset, in milliseconds, of `timeZone` at the given instant, defined so that
 * `wallClockAsIfUtc = actualUtc + offset`. Positive east of UTC.
 */
function zoneOffsetMs(timeZone: TimeZoneId, date: Date): number {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone,
        hourCycle: 'h23',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).formatToParts(date);

    const lookup: Record<string, number> = {};

    for (const part of parts) {
        if (part.type !== 'literal') {
            lookup[part.type] = Number(part.value);
        }
    }

    const wallClockAsIfUtc = Date.UTC(
        lookup.year,
        lookup.month - 1,
        lookup.day,
        lookup.hour,
        lookup.minute,
        lookup.second,
    );

    return wallClockAsIfUtc - date.getTime();
}

/**
 * Convert a UTC ISO timestamp into the `YYYY-MM-DDTHH:mm` string a native
 * `<input type="datetime-local">` expects, expressed as wall-clock in the zone.
 *
 * @returns The input value, or '' when the ISO string is invalid.
 */
export function utcIsoToZonedInput(iso: string, timeZone: TimeZoneId): string {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        hourCycle: 'h23',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).formatToParts(date);

    const lookup: Record<string, string> = {};

    for (const part of parts) {
        if (part.type !== 'literal') {
            lookup[part.type] = part.value;
        }
    }

    return `${lookup.year}-${lookup.month}-${lookup.day}T${lookup.hour}:${lookup.minute}`;
}

/**
 * Interpret a `datetime-local` wall-clock value (`YYYY-MM-DDTHH:mm`) as a time
 * in `timeZone` and return the corresponding UTC instant as an ISO string.
 *
 * @throws {Error} When the value is not a parseable local datetime.
 */
export function zonedInputToUtcIso(
    value: string,
    timeZone: TimeZoneId,
): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value);

    if (match === null) {
        throw new Error(`Invalid datetime-local value: "${value}"`);
    }

    const [, year, month, day, hour, minute] = match.map(Number);
    const wallClockAsIfUtc = Date.UTC(year, month - 1, day, hour, minute);

    // Approximate the instant to look up the offset, then correct for it. For
    // fixed-offset zones (SG/Jakarta/UTC) a single pass is exact.
    const offset = zoneOffsetMs(timeZone, new Date(wallClockAsIfUtc));

    return new Date(wallClockAsIfUtc - offset).toISOString();
}
