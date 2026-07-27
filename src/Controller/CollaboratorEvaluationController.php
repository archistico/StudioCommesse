<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\User;
use App\Query\CollaboratorEvaluationCriteria;
use App\Repository\ClientRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\CollaboratorEvaluationService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/controllo/collaboratori')]
#[IsGranted('ROLE_PARTNER')]
final class CollaboratorEvaluationController extends AbstractController
{
    #[Route('/{id}', name: 'app_control_collaborator_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function __invoke(
        User $user,
        Request $request,
        CollaboratorEvaluationService $evaluationService,
        ClientRepository $clientRepository,
        ProjectRepository $projectRepository,
        UserRepository $userRepository,
    ): Response {
        $defaultFrom = new DateTimeImmutable('first day of -5 months midnight');
        $defaultTo = new DateTimeImmutable('today midnight');
        $fromValue = trim((string) $request->query->get('from', ''));
        $toValue = trim((string) $request->query->get('to', ''));
        $from = $this->parseDate($fromValue) ?? $defaultFrom;
        $to = $this->parseDate($toValue) ?? $defaultTo;
        $dateError = null;

        if (('' !== $fromValue && null === $this->parseDate($fromValue))
            || ('' !== $toValue && null === $this->parseDate($toValue))
        ) {
            $dateError = 'Il periodo non è valido: sono state ripristinate le date predefinite.';
            $from = $defaultFrom;
            $to = $defaultTo;
        } elseif ($to < $from) {
            $dateError = 'La data finale non può precedere quella iniziale: è stato ripristinato il periodo predefinito.';
            $from = $defaultFrom;
            $to = $defaultTo;
        }

        $selectedClient = $this->findClient($request, $clientRepository);
        $selectedResponsible = $this->findResponsible($request, $userRepository);
        $selectedProject = $this->findProject($request, $projectRepository);
        if ($selectedProject instanceof Project
            && $selectedClient instanceof Client
            && $selectedProject->getClient()?->getId() !== $selectedClient->getId()
        ) {
            $selectedProject = null;
        }
        if ($selectedProject instanceof Project
            && $selectedResponsible instanceof User
            && $selectedProject->getResponsible()?->getId() !== $selectedResponsible->getId()
        ) {
            $selectedProject = null;
        }

        $billableValue = (string) $request->query->get('billable', '');
        $billable = match ($billableValue) {
            '1' => true,
            '0' => false,
            default => null,
        };

        $criteria = new CollaboratorEvaluationCriteria(
            userId: $user->getId() ?? 0,
            periodFrom: $from,
            periodBefore: $to->modify('+1 day'),
            clientId: $selectedClient?->getId(),
            responsibleId: $selectedResponsible?->getId(),
            projectId: $selectedProject?->getId(),
            billable: $billable,
        );
        $evaluation = $evaluationService->build($user, $criteria);

        $controlContext = $this->controlContext($request);
        $controlQuery = array_filter([
            'client' => $selectedClient?->getId(),
            'responsible' => $selectedResponsible?->getId(),
            'closure' => $controlContext['closure'],
            'critical' => $controlContext['critical'],
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'sort' => $controlContext['sort'],
            'direction' => $controlContext['direction'],
        ], static fn (mixed $value): bool => null !== $value && '' !== $value);
        $timeReportQuery = array_filter([
            'user' => $user->getId(),
            'project' => $selectedProject?->getId(),
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'billable' => null === $billable ? null : ($billable ? '1' : '0'),
        ], static fn (mixed $value): bool => null !== $value);

        return $this->render('control/collaborator.html.twig', [
            'evaluation' => $evaluation,
            'criteria' => $criteria,
            'clients_filter' => $clientRepository->findFiltered(null, false),
            'responsibles_filter' => $userRepository->findAllOrdered(),
            'projects_filter' => $projectRepository->findForEconomics(),
            'selected_client' => $selectedClient,
            'selected_responsible' => $selectedResponsible,
            'selected_project' => $selectedProject,
            'billable_value' => $billableValue,
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'date_error' => $dateError,
            'control_query' => $controlQuery,
            'control_context' => $controlContext,
            'time_report_query' => $timeReportQuery,
        ]);
    }

    /** @return array{closure: string, critical: string, sort: string, direction: string} */
    private function controlContext(Request $request): array
    {
        $scalar = static function (mixed $value): string {
            return is_scalar($value) ? (string) $value : '';
        };

        return [
            'closure' => $scalar($request->query->get('closure', '')),
            'critical' => '1' === $scalar($request->query->get('critical', '')) ? '1' : '',
            'sort' => $scalar($request->query->get('sort', '')),
            'direction' => $scalar($request->query->get('direction', '')),
        ];
    }

    private function findClient(Request $request, ClientRepository $repository): ?Client
    {
        $id = $this->positiveInt($request->query->get('client'));
        if (null === $id) {
            return null;
        }

        $client = $repository->find($id);

        return $client instanceof Client ? $client : null;
    }

    private function findResponsible(Request $request, UserRepository $repository): ?User
    {
        $id = $this->positiveInt($request->query->get('responsible'));
        if (null === $id) {
            return null;
        }

        $user = $repository->find($id);

        return $user instanceof User ? $user : null;
    }

    private function findProject(Request $request, ProjectRepository $repository): ?Project
    {
        $id = $this->positiveInt($request->query->get('project'));
        if (null === $id) {
            return null;
        }

        $project = $repository->find($id);

        return $project instanceof Project && !$project->isArchived() ? $project : null;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = (string) $value;

        return ctype_digit($normalized) && (int) $normalized > 0 ? (int) $normalized : null;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
