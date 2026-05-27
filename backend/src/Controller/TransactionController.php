<?php

namespace App\Controller;

use App\Api\EntityPresenter;
use App\Entity\User;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class TransactionController extends AbstractController
{
    /**
     * Transaction History: returns only the authenticated user's transactions.
     */
    #[Route('/api/transactions', name: 'api_transactions', methods: ['GET'])]
    public function list(TransactionRepository $transactions): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = array_map(
            EntityPresenter::transaction(...),
            $transactions->findByUser($user),
        );

        return $this->json(['transactions' => $data]);
    }
}
