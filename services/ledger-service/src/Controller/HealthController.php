<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * HealthController provides a liveness probe endpoint for the Ledger Service.
 *
 * This controller exposes a single GET /health endpoint that returns a simple
 * JSON status payload. It is used by Docker health checks and monitoring tools
 * to verify that the PHP-FPM process is alive and serving requests.
 *
 * @package App\Controller
 */
class HealthController extends AbstractController
{
    /**
     * Returns the health status of the Ledger Service.
     *
     * Responds with HTTP 200 and a JSON body of {"status":"ok"} whenever the
     * service process is running. No database or downstream connectivity is
     * checked — if this process is alive, the endpoint succeeds.
     *
     * Request format : GET /health (no request body or parameters)
     * Response format: {"status": "ok"} with HTTP 200
     *
     * @Route("/ledger/health", name="health_check", methods={"GET"})
     *
     * @return JsonResponse JSON response containing the service status
     */
    #[Route('/ledger/health', name: 'health_check', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok'], 200);
    }
}
