<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    /**
     * Liveness/readiness probe. Used by Docker healthchecks and DigitalOcean App Platform.
     */
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(Connection $connection): JsonResponse
    {
        $dbOk = true;
        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $dbOk = false;
        }

        return new JsonResponse([
            'status' => $dbOk ? 'ok' : 'degraded',
            'database' => $dbOk ? 'up' : 'down',
            'time' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ], $dbOk ? 200 : 503);
    }
}
