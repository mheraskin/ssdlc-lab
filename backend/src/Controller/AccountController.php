<?php

namespace App\Controller;

use App\Api\EntityPresenter;
use App\Entity\User;
use App\Repository\AccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class AccountController extends AbstractController
{
    /**
     * Account Service: a client sees ONLY their own accounts. The query is scoped by the
     * authenticated user, so ownership is enforced at the data-access layer too.
     */
    #[Route('/api/accounts', name: 'api_accounts', methods: ['GET'])]
    public function list(AccountRepository $accounts): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = array_map(
            EntityPresenter::account(...),
            $accounts->findByUser($user),
        );

        return $this->json(['accounts' => $data]);
    }
}
