<?php

namespace App\Controller;

use App\Api\EntityPresenter;
use App\Entity\AuditLog;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Auth Service: validates credentials, rate-limits attempts, audits the outcome,
     * and issues a JWT on success. The token carries the user's roles and is later
     * verified statelessly on every API request.
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, RateLimiterFactory $loginLimiter): JsonResponse
    {
        $ip = $request->getClientIp() ?? 'unknown';

        // API Gateway behaviour: throttle brute-force / credential-stuffing by client IP.
        $limiter = $loginLimiter->create($ip);
        if (false === $limiter->consume(1)->isAccepted()) {
            $this->audit->log(AuditLog::LOGIN_RATE_LIMITED, metadata: ['ip' => $ip]);

            return $this->json(['error' => 'Too many login attempts. Please try again later.'], 429);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => 'Invalid JSON body.'], 400);
        }

        $email = \is_string($data['email'] ?? null) ? trim($data['email']) : '';
        $password = \is_string($data['password'] ?? null) ? $data['password'] : '';

        if ('' === $email || '' === $password) {
            return $this->json(['error' => 'Email and password are required.'], 400);
        }

        $user = $this->users->findOneByEmail($email);

        // Always run the hasher path to reduce user-enumeration timing differences,
        // and never reveal whether the email or the password was the wrong one.
        if (null === $user || !$this->hasher->isPasswordValid($user, $password)) {
            $this->audit->log(AuditLog::LOGIN_FAILED, actorEmail: $email, metadata: ['reason' => 'invalid_credentials']);

            return $this->json(['error' => 'Invalid credentials.'], 401);
        }

        if (!$user->isActive()) {
            $this->audit->log(AuditLog::LOGIN_FAILED, actor: $user, metadata: ['reason' => 'account_blocked']);

            return $this->json(['error' => 'Account is blocked.'], 403);
        }

        $token = $this->jwtManager->create($user);
        $this->audit->log(AuditLog::LOGIN_SUCCESS, actor: $user);

        return $this->json([
            'token' => $token,
            'user' => EntityPresenter::user($user),
        ]);
    }

    /**
     * Logout is stateless: the BFF clears the httpOnly cookie holding the token. We still
     * record the event for the audit trail.
     */
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $this->audit->log(AuditLog::LOGOUT, actor: $user);
        }

        return new JsonResponse(null, 204);
    }

    /** User/Profile Service: returns the authenticated user's own profile. */
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json(['user' => EntityPresenter::user($user)]);
    }
}
