<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\OverallClosureStatus;
use App\Query\ControlSearchCriteria;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use App\Service\ProjectControlService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/controllo')]
#[IsGranted('ROLE_PARTNER')]
final class ControlController extends AbstractController
{
    private const SESSION_KEY = 'studio_commesse.control_filters';

    /** @var list<string> */
    private const FILTER_KEYS = ['client', 'responsible', 'closure', 'critical', 'from', 'to', 'sort', 'direction'];

    #[Route('', name: 'app_control_index', methods: ['GET'])]
    public function __invoke(
        Request $request,
        ProjectControlService $controlService,
        ClientRepository $clientRepository,
        UserRepository $userRepository,
    ): Response {
        $session = $request->getSession();
        if ($request->query->getBoolean('reset')) {
            $session->remove(self::SESSION_KEY);

            return $this->redirectToRoute('app_control_index');
        }

        $values = $this->resolveValues($request);
        $dateError = null;
        $defaultFrom = new DateTimeImmutable('first day of -5 months midnight');
        $defaultTo = new DateTimeImmutable('today midnight');
        $from = $this->parseDate($values['from'] ?? '') ?? $defaultFrom;
        $to = $this->parseDate($values['to'] ?? '') ?? $defaultTo;

        if (isset($values['from']) && '' !== $values['from'] && null === $this->parseDate($values['from'])) {
            $dateError = 'La data iniziale non è valida: è stato ripristinato il periodo predefinito.';
        }
        if (isset($values['to']) && '' !== $values['to'] && null === $this->parseDate($values['to'])) {
            $dateError = 'La data finale non è valida: è stato ripristinato il periodo predefinito.';
        }
        if ($to < $from) {
            $dateError = 'La data finale non può precedere quella iniziale: è stato ripristinato il periodo predefinito.';
            $from = $defaultFrom;
            $to = $defaultTo;
        }

        $values['from'] = $from->format('Y-m-d');
        $values['to'] = $to->format('Y-m-d');
        $session->set(self::SESSION_KEY, $values);

        $clientId = $this->positiveInt($values['client'] ?? '');
        $responsibleId = $this->positiveInt($values['responsible'] ?? '');
        $sort = in_array($values['sort'] ?? '', ControlSearchCriteria::SORTS, true)
            ? $values['sort']
            : ControlSearchCriteria::SORT_CRITICALITY;
        $direction = 'asc' === ($values['direction'] ?? '') ? 'asc' : 'desc';
        $criteria = new ControlSearchCriteria(
            clientId: $clientId,
            responsibleId: $responsibleId,
            overallStatus: OverallClosureStatus::tryFrom($values['closure'] ?? ''),
            onlyCritical: '1' === ($values['critical'] ?? ''),
            periodFrom: $from,
            periodBefore: $to->modify('+1 day'),
            sort: $sort,
            direction: $direction,
        );

        return $this->render('control/index.html.twig', [
            'dashboard' => $controlService->build($criteria),
            'criteria' => $criteria,
            'clients_filter' => $clientRepository->findFiltered(null, false),
            'responsibles_filter' => $userRepository->findAllOrdered(),
            'closure_statuses' => OverallClosureStatus::cases(),
            'sort_options' => [
                ControlSearchCriteria::SORT_CRITICALITY => 'Criticità',
                ControlSearchCriteria::SORT_DUE_DATE => 'Scadenza',
                ControlSearchCriteria::SORT_CODE => 'Codice commessa',
                ControlSearchCriteria::SORT_ACTUAL_HOURS => 'Ore consuntivate',
                ControlSearchCriteria::SORT_TIME_DEVIATION => 'Scostamento ore',
                ControlSearchCriteria::SORT_MARGIN => 'Margine',
            ],
            'selected_client_id' => $clientId,
            'selected_responsible_id' => $responsibleId,
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'date_error' => $dateError,
            'collaborator_detail_query' => array_filter([
                'client' => $clientId,
                'responsible' => $responsibleId,
                'closure' => $criteria->overallStatus?->value,
                'critical' => $criteria->onlyCritical ? '1' : null,
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'sort' => $criteria->sort,
                'direction' => $criteria->direction,
            ], static fn (mixed $value): bool => null !== $value && '' !== $value),
            'stalled_days' => ProjectControlService::STALLED_AFTER_DAYS,
            'overload_activity_count' => ProjectControlService::OVERLOAD_ACTIVITY_COUNT,
            'overload_remaining_minutes' => ProjectControlService::OVERLOAD_REMAINING_MINUTES,
        ]);
    }

    /** @return array<string, string> */
    private function resolveValues(Request $request): array
    {
        $session = $request->getSession();
        $values = $this->normalizeStoredValues($session->get(self::SESSION_KEY, []));
        $queryValues = $request->query->all();
        $hasRecognizedFilter = false;

        foreach (self::FILTER_KEYS as $key) {
            if (!array_key_exists($key, $queryValues)) {
                continue;
            }

            $hasRecognizedFilter = true;
            $values[$key] = $request->query->get($key, '');
        }

        if ($hasRecognizedFilter) {
            $session->set(self::SESSION_KEY, $values);
        }

        return $values;
    }

    /**
     * @param mixed $stored
     * @return array<string, string>
     */
    private function normalizeStoredValues(mixed $stored): array
    {
        if (!is_array($stored)) {
            $stored = [];
        }

        $values = [];
        foreach (self::FILTER_KEYS as $key) {
            $value = $stored[$key] ?? '';
            $values[$key] = is_scalar($value) ? (string) $value : '';
        }

        return $values;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function positiveInt(string $value): ?int
    {
        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }
}
