<?php

namespace App\Repository;

use App\Entity\MfaChallenge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MfaChallenge>
 */
class MfaChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MfaChallenge::class);
    }

    public function findPendingForTransaction(int $transactionId): ?MfaChallenge
    {
        return $this->findOneBy([
            'relatedTransactionId' => $transactionId,
            'status' => MfaChallenge::STATUS_PENDING,
        ]);
    }
}
