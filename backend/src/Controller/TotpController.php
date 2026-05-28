<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\TotpService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Enrollment + management of the user's TOTP (authenticator-app) second factor.
 * All endpoints require an authenticated user.
 */
#[Route('/api/totp')]
#[IsGranted('ROLE_USER')]
class TotpController extends AbstractController
{
    public function __construct(private readonly TotpService $totp)
    {
    }

    /**
     * Begin enrollment: generate a fresh TOTP secret and return it + the otpauth:// URI for
     * the QR. The user is NOT yet considered to have MFA enabled — they must confirm the
     * first code from their authenticator via /enable.
     */
    #[Route('/setup', name: 'api_totp_setup', methods: ['POST'])]
    public function setup(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $this->totp->startEnrollment($user);

        return $this->json([
            'secret' => $data['secret'],
            'provisioningUri' => $data['provisioningUri'],
            'enabled' => false,
        ]);
    }

    /** Confirm enrollment by entering the first 6-digit code from the authenticator. */
    #[Route('/enable', name: 'api_totp_enable', methods: ['POST'])]
    public function enable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $code = $this->extractCode($request);

        if ('' === $code || !$this->totp->confirmEnrollment($user, $code)) {
            return $this->json(['error' => 'Invalid or expired code. Try again with the latest code from your authenticator.'], 422);
        }

        return $this->json(['enabled' => true]);
    }

    /**
     * Disable TOTP. Requires the current code from the authenticator (i.e. re-authentication
     * via the second factor itself) so an attacker with only a session cookie cannot turn it off.
     */
    #[Route('/disable', name: 'api_totp_disable', methods: ['POST'])]
    public function disable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $code = $this->extractCode($request);

        if (!$user->isTotpEnabled()) {
            return $this->json(['enabled' => false]);
        }
        if ('' === $code || !$this->totp->verify($user, $code)) {
            return $this->json(['error' => 'A valid authenticator code is required to disable MFA.'], 422);
        }

        $this->totp->disable($user);

        return $this->json(['enabled' => false]);
    }

    private function extractCode(Request $request): string
    {
        try {
            $body = $request->toArray();
        } catch (\Throwable) {
            return '';
        }

        return \is_string($body['code'] ?? null) ? trim($body['code']) : '';
    }
}
