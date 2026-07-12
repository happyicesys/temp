<?php

namespace App\Http\Controllers;

use App\Http\Resources\TempResource;
use App\Models\Device;
use App\Models\Temp;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class VendTempController extends Controller
{
    /** Default window (in hours) when no explicit range is supplied. */
    private const DEFAULT_WINDOW_HOURS = 1;

    /**
     * Render the temperature / humidity chart for a single device.
     */
    public function index(Request $request, Device $device): Response
    {
        [$from, $to] = $this->resolveRange($request);

        $readings = Temp::query()
            ->where('device_id', $device->getKey())
            ->between($from, $to)
            ->orderBy('recorded_at')
            ->get();

        return Inertia::render('VendTemps/Index', [
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'asset_code' => $device->asset_code,
                'location' => $device->location,
            ],
            'devices' => Device::query()
                ->orderBy('name')
                ->get(['id', 'name', 'asset_code'])
                ->map(fn (Device $d): array => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'asset_code' => $d->asset_code,
                ]),
            // resolve() unwraps the resource collection's default `data` key so
            // the Vue layer receives a plain array of readings.
            'readings' => TempResource::collection($readings)->resolve($request),
            'filters' => [
                'datetime_from' => $from->toIso8601String(),
                'datetime_to' => $to->toIso8601String(),
            ],
        ]);
    }

    /**
     * Resolve the [from, to] window from the request, falling back to the
     * default trailing window.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $to = $request->date('datetime_to') ?? Carbon::now();
        $from = $request->date('datetime_from')
            ?? $to->copy()->subHours(self::DEFAULT_WINDOW_HOURS);

        // Guard against an inverted range coming from the query string.
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
