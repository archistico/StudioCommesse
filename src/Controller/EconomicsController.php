<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Expense;
use App\Entity\Payment;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Form\ExpenseType;
use App\Form\PaymentType;
use App\Repository\ExpenseRepository;
use App\Repository\PaymentRepository;
use App\Repository\ProjectRepository;
use App\Security\Voter\ExpenseVoter;
use App\Service\AuditRecord;
use App\Service\AuditedTransaction;
use App\Service\ProjectFinancialService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/economia')]
#[IsGranted('ROLE_COLLABORATOR')]
final class EconomicsController extends AbstractController
{
    #[Route('', name: 'app_economics_index', methods: ['GET'])]
    #[IsGranted('ROLE_PARTNER')]
    public function index(ProjectRepository $projectRepository, ProjectFinancialService $financialService): Response
    {
        $summaries = $financialService->summarizeMany($projectRepository->findForEconomics());

        $totals = [
            'estimated' => 0,
            'cost' => 0,
            'payments' => 0,
            'remaining' => 0,
            'over_budget' => 0,
        ];
        foreach ($summaries as $summary) {
            $totals['estimated'] += $summary->estimatedAmountCents;
            $totals['cost'] += $summary->getTotalCostCents();
            $totals['payments'] += $summary->paymentsCents;
            $totals['remaining'] += $summary->getRemainingToCollectCents();
            if ($summary->isOverBudget()) {
                ++$totals['over_budget'];
            }
        }

        return $this->render('economics/index.html.twig', [
            'summaries' => $summaries,
            'client_summaries' => $financialService->summarizeByClient($summaries),
            'totals' => $totals,
        ]);
    }

    #[Route('/commessa/{id}', name: 'app_economics_project', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(
        Project $project,
        ExpenseRepository $expenseRepository,
        PaymentRepository $paymentRepository,
        ProjectFinancialService $financialService,
    ): Response {
        $user = $this->requireCurrentUser();
        $isPartner = $user->isPartner();

        return $this->render('economics/show.html.twig', [
            'project' => $project,
            'is_partner_view' => $isPartner,
            'summary' => $isPartner ? $financialService->summarize($project) : null,
            'expenses' => $isPartner
                ? $expenseRepository->findForProject($project)
                : $expenseRepository->findForProjectAndRecorder($project, $user),
            'own_expense_total_cents' => $isPartner ? null : $expenseRepository->sumCentsForProjectAndRecorder($project, $user),
            'payments' => $isPartner ? $paymentRepository->findForProject($project) : [],
        ]);
    }

    #[Route('/commessa/{id}/spesa', name: 'app_expense_new', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function newExpense(
        Project $project,
        Request $request,
        ExpenseRepository $repository,
        AuditedTransaction $transaction,
    ): Response {
        if ($project->isArchived()) {
            throw $this->createAccessDeniedException('Le spese di una commessa archiviata sono in sola lettura.');
        }

        $expense = (new Expense())->setProject($project)->setRecordedBy($this->requireCurrentUser());

        return $this->handleExpenseForm($expense, $project, $request, $repository, $transaction, true);
    }

    #[Route('/spesa/{id}/modifica', name: 'app_expense_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function editExpense(
        Expense $expense,
        Request $request,
        ExpenseRepository $repository,
        AuditedTransaction $transaction,
    ): Response {
        $project = $expense->getProject();
        if (!$project instanceof Project) {
            throw new \LogicException('La spesa non è associata a una commessa.');
        }
        $this->denyAccessUnlessGranted(ExpenseVoter::MANAGE, $expense);

        return $this->handleExpenseForm($expense, $project, $request, $repository, $transaction, false);
    }

    #[Route('/spesa/{id}/elimina', name: 'app_expense_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deleteExpense(
        Expense $expense,
        Request $request,
        ExpenseRepository $repository,
        AuditedTransaction $transaction,
    ): Response {
        $project = $expense->getProject();
        if (!$project instanceof Project) {
            throw new \LogicException('La spesa non è associata a una commessa.');
        }
        $this->denyAccessUnlessGranted(ExpenseVoter::MANAGE, $expense);
        $this->validateCsrf('delete_expense_'.$expense->getId(), $request);

        $id = $expense->getId();
        $actorIdentifier = $this->requireCurrentUser()->getUserIdentifier();
        $transaction->execute(
            static function () use ($repository, $expense): ?int {
                $repository->remove($expense, false);

                return $expense->getId();
            },
            static fn (?int $deletedId): AuditRecord => new AuditRecord(
                AuditAction::ExpenseDeleted,
                $actorIdentifier,
                Expense::class,
                $deletedId ?? $id,
                ['project' => $project->getCode()],
                $request->getClientIp(),
            ),
        );
        $this->addFlash('success', 'Spesa eliminata.');

        return $this->redirectToRoute('app_economics_project', ['id' => $project->getId()]);
    }

    #[Route('/commessa/{id}/incasso', name: 'app_payment_new', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PARTNER')]
    public function newPayment(
        Project $project,
        Request $request,
        PaymentRepository $repository,
        AuditedTransaction $transaction,
    ): Response {
        $this->denyIfProjectArchived($project, 'Gli incassi di una commessa archiviata sono in sola lettura.');
        $payment = (new Payment())->setProject($project)->setRecordedBy($this->requireCurrentUser());

        return $this->handlePaymentForm($payment, $project, $request, $repository, $transaction, true);
    }

    #[Route('/incasso/{id}/modifica', name: 'app_payment_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PARTNER')]
    public function editPayment(
        Payment $payment,
        Request $request,
        PaymentRepository $repository,
        AuditedTransaction $transaction,
    ): Response {
        $project = $payment->getProject();
        if (!$project instanceof Project) {
            throw new \LogicException('L’incasso non è associato a una commessa.');
        }

        $this->denyIfProjectArchived($project, 'Gli incassi di una commessa archiviata sono in sola lettura.');

        return $this->handlePaymentForm($payment, $project, $request, $repository, $transaction, false);
    }

    #[Route('/incasso/{id}/elimina', name: 'app_payment_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_PARTNER')]
    public function deletePayment(
        Payment $payment,
        Request $request,
        PaymentRepository $repository,
        AuditedTransaction $transaction,
    ): Response {
        $project = $payment->getProject();
        if (!$project instanceof Project) {
            throw new \LogicException('L’incasso non è associato a una commessa.');
        }
        $this->denyIfProjectArchived($project, 'Gli incassi di una commessa archiviata sono in sola lettura.');
        $this->validateCsrf('delete_payment_'.$payment->getId(), $request);

        $id = $payment->getId();
        $actorIdentifier = $this->requireCurrentUser()->getUserIdentifier();
        $transaction->execute(
            static function () use ($repository, $payment): ?int {
                $repository->remove($payment, false);

                return $payment->getId();
            },
            static fn (?int $deletedId): AuditRecord => new AuditRecord(
                AuditAction::PaymentDeleted,
                $actorIdentifier,
                Payment::class,
                $deletedId ?? $id,
                ['project' => $project->getCode()],
                $request->getClientIp(),
            ),
        );
        $this->addFlash('success', 'Incasso eliminato.');

        return $this->redirectToRoute('app_economics_project', ['id' => $project->getId()]);
    }

    private function handleExpenseForm(
        Expense $expense,
        Project $project,
        Request $request,
        ExpenseRepository $repository,
        AuditedTransaction $transaction,
        bool $isNew,
    ): Response {
        $form = $this->createForm(ExpenseType::class, $expense, ['project' => $project]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $actorIdentifier = $this->requireCurrentUser()->getUserIdentifier();
            $transaction->execute(
                static function () use ($repository, $expense): Expense {
                    $repository->save($expense, false);

                    return $expense;
                },
                static fn (Expense $saved): AuditRecord => new AuditRecord(
                    $isNew ? AuditAction::ExpenseCreated : AuditAction::ExpenseUpdated,
                    $actorIdentifier,
                    Expense::class,
                    $saved->getId(),
                    ['project' => $project->getCode(), 'amount_cents' => $saved->getAmountCents()],
                    $request->getClientIp(),
                ),
            );
            $this->addFlash('success', $isNew ? 'Spesa registrata.' : 'Spesa aggiornata.');

            return $this->redirectToRoute('app_economics_project', ['id' => $project->getId()]);
        }

        return $this->render('economics/form.html.twig', [
            'form' => $form,
            'title' => $isNew ? 'Nuova spesa' : 'Modifica spesa',
            'project' => $project,
            'expense' => $expense,
            'is_new' => $isNew,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function handlePaymentForm(
        Payment $payment,
        Project $project,
        Request $request,
        PaymentRepository $repository,
        AuditedTransaction $transaction,
        bool $isNew,
    ): Response {
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $actorIdentifier = $this->requireCurrentUser()->getUserIdentifier();
            $transaction->execute(
                static function () use ($repository, $payment): Payment {
                    $repository->save($payment, false);

                    return $payment;
                },
                static fn (Payment $saved): AuditRecord => new AuditRecord(
                    $isNew ? AuditAction::PaymentCreated : AuditAction::PaymentUpdated,
                    $actorIdentifier,
                    Payment::class,
                    $saved->getId(),
                    ['project' => $project->getCode(), 'amount_cents' => $saved->getAmountCents()],
                    $request->getClientIp(),
                ),
            );
            $this->addFlash('success', $isNew ? 'Incasso registrato.' : 'Incasso aggiornato.');

            return $this->redirectToRoute('app_economics_project', ['id' => $project->getId()]);
        }

        return $this->render('economics/form.html.twig', [
            'form' => $form,
            'title' => $isNew ? 'Nuovo incasso' : 'Modifica incasso',
            'project' => $project,
            'payment' => $payment,
            'is_new' => $isNew,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function denyIfProjectArchived(Project $project, string $message): void
    {
        if ($project->isArchived()) {
            throw $this->createAccessDeniedException($message);
        }
    }

    private function validateCsrf(string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Token CSRF non valido.');
        }
    }

    private function requireCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
