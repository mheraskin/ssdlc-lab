<?php

namespace App\Controller;

use App\Api\EntityPresenter;
use App\Dto\CreatePaymentRequest;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use App\Security\AccountVoter;
use App\Service\PaymentResult;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly AccountRepository $accounts,
        private readonly TransactionRepository $transactions,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Payment Service entry point. Validates input, checks account ownership (voter),
     * then delegates all business/risk/balance checks to PaymentService.
     */
    #[Route('/api/payments', name: 'api_payments_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => 'Invalid JSON body.'], 400);
        }

        $dto = new CreatePaymentRequest();
        $dto->sourceAccountId = isset($data['sourceAccountId']) ? (int) $data['sourceAccountId'] : null;
        $dto->recipientName = (string) ($data['recipientName'] ?? '');
        $dto->recipientAccount = (string) ($data['recipientAccount'] ?? '');
        $dto->amount = isset($data['amount']) ? (float) $data['amount'] : null;

        $violations = $this->validator->validate($dto);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $v) {
                $errors[$v->getPropertyPath()] = $v->getMessage();
            }

            return $this->json(['error' => 'Validation failed.', 'fields' => $errors], 422);
        }

        $account = $this->accounts->find($dto->sourceAccountId);
        if (null === $account) {
            return $this->json(['error' => 'Source account not found.'], 404);
        }

        // RBAC: you may only pay FROM an account you own.
        $this->denyAccessUnlessGranted(AccountVoter::USE, $account);

        $result = $this->payments->createPayment(
            $user,
            $account,
            trim($dto->recipientName),
            trim($dto->recipientAccount),
            $dto->amountInCents(),
        );

        return $this->respond($result);
    }

    /**
     * Confirm a risky payment with the MFA code.
     */
    #[Route('/api/payments/{id}/confirm', name: 'api_payments_confirm', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function confirm(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $transaction = $this->transactions->find($id);
        if (null === $transaction) {
            return $this->json(['error' => 'Payment not found.'], 404);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => 'Invalid JSON body.'], 400);
        }

        $code = \is_string($data['code'] ?? null) ? trim($data['code']) : '';
        if ('' === $code) {
            return $this->json(['error' => 'Confirmation code is required.'], 400);
        }

        $result = $this->payments->confirmPayment($user, $transaction, $code);

        return $this->respond($result);
    }

    private function respond(PaymentResult $result): JsonResponse
    {
        $status = match ($result->status) {
            PaymentResult::STATUS_COMPLETED => 201,
            PaymentResult::STATUS_MFA_REQUIRED => 202,
            default => 422,
        };

        $payload = [
            'status' => $result->status,
            'message' => $result->message,
        ];
        if (null !== $result->transaction) {
            $payload['transaction'] = EntityPresenter::transaction($result->transaction);
        }

        return $this->json($payload, $status);
    }
}
