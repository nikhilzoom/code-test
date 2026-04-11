<?php

declare(strict_types=1);

namespace PhpCommon\DTO;

/**
 * Data Transfer Object representing a service health check response.
 *
 * Returned by every service's GET /health endpoint to indicate
 * that the PHP-FPM process is alive and accepting requests.
 */
class HealthResponseDTO
{
    /**
     * The health status string.
     *
     * Expected value is "ok" when the service is healthy.
     *
     * @var string
     */
    public string $status;

    /**
     * Construct a new HealthResponseDTO.
     *
     * @param string $status The health status value (e.g. "ok").
     */
    public function __construct(string $status = 'ok')
    {
        $this->status = $status;
    }
}
