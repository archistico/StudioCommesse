<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\UserRole;
use App\Query\CollaboratorReportCriteria;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\CollaboratorReportService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report/collaboratori')]
#[IsGranted('ROLE_PARTNER')]
final class CollaboratorReportController extends AbstractController
{
    public function __construct(
        private readonly CollaboratorReportService $reportService,
        private readonly UserRepository $users,
        private readonly ProjectRepository $projects,
    ) {
    }

    #[Route('', name: 'app_collaborator_report_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('collaborator_report/index.html.twig', $this->context($request));
    }

    #[Route('/{id}', name: 'app_collaborator_report_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(User $user, Request $request): Response
    {
        if (UserRole::Collaborator !== $user->getRole()) {
            throw $this->createNotFoundException('Collaboratore non trovato.');
        }
        $context = $this->context($request, $user);

        return $this->render('collaborator_report/show.html.twig', $context + ['person' => $context['people'][0]]);
    }

    #[Route('/riepilogo.csv', name: 'app_collaborator_report_csv', methods: ['GET'])]
    #[Route('/ore.csv', name: 'app_collaborator_report_entries_csv', methods: ['GET'])]
    public function csv(Request $request): Response
    {
        $context = $this->context($request);
        if (null !== $context['date_error']) {
            throw new BadRequestHttpException($context['date_error']);
        }
        $entries = 'app_collaborator_report_entries_csv' === $request->attributes->get('_route');
        $rows = [$entries
            ? ['Collaboratore', 'Data', 'Inizio', 'Fine', 'Commessa', 'Attività', 'Lavoro svolto', 'Durata minuti', 'Fatturabile', 'Costo storico euro']
            : ['Collaboratore', 'Dal', 'Al', 'Minuti nel periodo', 'Minuti fatturabili', 'Costo storico euro', 'Attività aperte oggi', 'Attività scadute oggi', 'Attività in attesa oggi', 'Completate nel periodo', 'Timer aperti oggi']];
        foreach ($context['people'] as $person) {
            if ($entries) {
                foreach ($person->entries as $entry) {
                    $rows[] = [$person->name, $entry->startedAt->format('d/m/Y'), $entry->startedAt->format('H:i'), $entry->endedAt?->format('H:i') ?? '',
                        $entry->projectCode.' · '.$entry->projectName, $entry->activityTitle, $entry->description ?? '',
                        $entry->minutes, $entry->billable ? 'Sì' : 'No', number_format($entry->costCents / 100, 2, ',', '')];
                }
            } else {
                $rows[] = [$person->name, $context['criteria']->from->format('d/m/Y'), $context['criteria']->to->format('d/m/Y'),
                    $person->minutes, $person->billableMinutes, number_format($person->costCents / 100, 2, ',', ''),
                    count($person->openActivities), $person->overdueCount(), $person->waitingCount(), count($person->completedActivities), count($person->timers)];
            }
        }
        $stream = fopen('php://temp', 'w+b');
        if (false === $stream) {
            throw new \RuntimeException('Impossibile generare il report.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            $safeRow = array_map(static fn (string|int $cell): string|int => is_string($cell) && preg_match('/^\s*[=+@-]/u', $cell) ? "'".$cell : $cell, $row);
            fputcsv($stream, $safeRow, ';', '"', '', "\r\n");
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        if (false === $content) {
            throw new \RuntimeException('Impossibile leggere il report.');
        }
        $filename = sprintf('report-collaboratori-%s-%s-%s.csv', $entries ? 'ore' : 'riepilogo', $context['criteria']->from->format('Y-m-d'), $context['criteria']->to->format('Y-m-d'));

        return new Response($content, headers: [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        ]);
    }

    /** @return array{criteria: CollaboratorReportCriteria, period: string, date_error: ?string, people: list<\App\Query\CollaboratorReportPerson>, users_filter: list<User>, projects_filter: list<Project>, current_query: array<string, int|string|null>, filter_query: array<string, int|null>, totals: array{minutes: int, contributors: int, overdue: int, waiting: int}} */
    private function context(Request $request, ?User $person = null): array
    {
        $today = new DateTimeImmutable('today');
        $period = $request->query->getString('period', 'week');
        $fromValue = $request->query->getString('from');
        $toValue = $request->query->getString('to');
        $error = null;
        if ('' !== $fromValue || '' !== $toValue) {
            // Explicit dates win, also when following a link from a filtered report.
            $from = $this->date($fromValue);
            $to = $this->date($toValue);
            if (null === $from || null === $to || $from > $to) {
                $error = 'Periodo non valido. È stata ripristinata la settimana corrente.';
                $period = 'week';
                $from = $today->modify('monday this week');
                $to = $today;
            } else {
                $expectedFrom = match ($period) {
                    'today' => $today,
                    'week' => $today->modify('monday this week'),
                    'month' => $today->modify('first day of this month'),
                    default => null,
                };
                if ($from != $expectedFrom || $to != $today) {
                    $period = 'custom';
                }
            }
        } else {
            $from = match ($period) {
                'today' => $today,
                'month' => $today->modify('first day of this month'),
                default => $today->modify('monday this week'),
            };
            if (!in_array($period, ['today', 'week', 'month'], true)) {
                $error = 'Scegli un periodo valido. È stata ripristinata la settimana corrente.';
                $period = 'week';
            }
            $to = $today;
        }
        $userId = $person?->getId() ?? $this->id($request, 'user');
        if (null !== $userId) {
            $user = $this->users->find($userId);
            if (!$user instanceof User || UserRole::Collaborator !== $user->getRole()) {
                throw $this->createNotFoundException('Collaboratore non trovato.');
            }
        }
        $projectId = $this->id($request, 'project');
        if (null !== $projectId && !$this->projects->find($projectId) instanceof Project) {
            throw $this->createNotFoundException('Commessa non trovata.');
        }
        $criteria = new CollaboratorReportCriteria($from, $to, $userId, $projectId);
        $people = $this->reportService->build($criteria);
        $totals = ['minutes' => 0, 'contributors' => 0, 'overdue' => 0, 'waiting' => 0];
        foreach ($people as $row) {
            $totals['minutes'] += $row->minutes;
            $totals['contributors'] += [] !== $row->entries ? 1 : 0;
            $totals['overdue'] += $row->overdueCount();
            $totals['waiting'] += $row->waitingCount();
        }

        return [
            'criteria' => $criteria, 'period' => $period, 'date_error' => $error, 'people' => $people,
            'users_filter' => $this->users->findBy(['role' => UserRole::Collaborator], ['displayName' => 'ASC']),
            'projects_filter' => $this->projects->findBy([], ['code' => 'DESC']),
            'current_query' => ['period' => $period, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'user' => $userId, 'project' => $projectId],
            'filter_query' => ['user' => $userId, 'project' => $projectId], 'totals' => $totals,
        ];
    }

    private function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function id(Request $request, string $key): ?int
    {
        $value = $request->query->getString($key);
        if ('' === $value) {
            return null;
        }
        if (!ctype_digit($value) || (int) $value <= 0 || (string) (int) $value !== $value) {
            throw new BadRequestHttpException('Filtro non valido: '.$key);
        }

        return (int) $value;
    }
}
