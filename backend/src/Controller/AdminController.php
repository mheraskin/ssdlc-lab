<?php

namespace App\Controller;

use App\Api\EntityPresenter;
use App\Entity\AuditLog;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Service. The whole controller requires ROLE_ADMIN (also enforced by the
 * access_control rule on ^/api/admin). Admins can review users, payments and the audit
 * log, and block/unblock users — but never see passwords or other secrets.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin')]
class AdminController extends AbstractController
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    #[Route('/users', name: 'api_admin_users', methods: ['GET'])]
    public function users(UserRepository $users): JsonResponse
    {
        $data = array_map(EntityPresenter::user(...), $users->findBy([], ['id' => 'ASC']));

        return $this->json(['users' => $data]);
    }

    #[Route('/payments', name: 'api_admin_payments', methods: ['GET'])]
    public function payments(TransactionRepository $transactions): JsonResponse
    {
        $data = array_map(EntityPresenter::transaction(...), $transactions->findAllRecent());

        return $this->json(['payments' => $data]);
    }

    #[Route('/audit-logs', name: 'api_admin_audit_logs', methods: ['GET'])]
    public function auditLogs(AuditLogRepository $logs): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();
        $this->audit->log(AuditLog::ADMIN_VIEWED_AUDIT_LOGS, actor: $admin);

        $data = array_map(EntityPresenter::auditLog(...), $logs->findRecent());

        return $this->json(['auditLogs' => $data]);
    }

    #[Route('/users/{id}/block', name: 'api_admin_user_block', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function block(int $id, UserRepository $users, EntityManagerInterface $em): JsonResponse
    {
        return $this->changeStatus($id, User::STATUS_BLOCKED, $users, $em);
    }

    #[Route('/users/{id}/unblock', name: 'api_admin_user_unblock', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function unblock(int $id, UserRepository $users, EntityManagerInterface $em): JsonResponse
    {
        return $this->changeStatus($id, User::STATUS_ACTIVE, $users, $em);
    }

    private function changeStatus(int $id, string $status, UserRepository $users, EntityManagerInterface $em): JsonResponse
    {
        $target = $users->find($id);
        if (null === $target) {
            return $this->json(['error' => 'User not found.'], 404);
        }

        /** @var User $admin */
        $admin = $this->getUser();
        $previous = $target->getStatus();
        $target->setStatus($status);
        $em->flush();

        $this->audit->log(
            AuditLog::ADMIN_CHANGED_USER_STATUS,
            actor: $admin,
            entityType: 'User',
            entityId: (string) $target->getId(),
            metadata: ['from' => $previous, 'to' => $status, 'targetEmail' => $target->getEmail()],
        );

        return $this->json(['user' => EntityPresenter::user($target)]);
    }
}
