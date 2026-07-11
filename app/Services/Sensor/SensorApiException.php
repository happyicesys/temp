<?php

namespace App\Services\Sensor;

use RuntimeException;

/**
 * Thrown when a vendor API returns an error response or unparseable body.
 *
 * Catching this distinct class lets the queued poll job mark the device as
 * unreachable without swallowing genuine code bugs (which surface as the
 * usual exceptions).
 */
class SensorApiException extends RuntimeException {}
