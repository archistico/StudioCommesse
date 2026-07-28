<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\MonthlyReportService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report/mensile')]
#[IsGranted('ROLE_PARTNER')]
final class MonthlyReportController extends AbstractController
{
    #[Route('', name: 'app_monthly_report', methods: ['GET'])]
    public function index(
        Request $request,
        MonthlyReportService $reportService,
        ProjectRepository $projectRepository,
    ): Response {
        $month = $this->month($request);
        $project = $this->project($request, $projectRepository);

        return $this->render('report/monthly.html.twig', [
            'report' => $reportService->build($month, $project?->getId()),
            'projects_filter' => $projectRepository->findForEconomics(),
            'selected_project' => $project,
            'month_value' => $month->format('Y-m'),
            'previous_month' => $month->modify('-1 month')->format('Y-m'),
            'next_month' => $month->modify('+1 month')->format('Y-m'),
        ]);
    }

    #[Route('/csv', name: 'app_monthly_report_csv', methods: ['GET'])]
    public function csv(
        Request $request,
        MonthlyReportService $reportService,
        ProjectRepository $projectRepository,
    ): Response {
        $month = $this->month($request);
        $project = $this->project($request, $projectRepository);
        $report = $reportService->build($month, $project?->getId());
        $filename = sprintf('report-mensile-%s%s.csv', $month->format('Y-m'), $project ? '-'.$project->getCode() : '');
        $output = fopen('php://temp', 'w+b');
        if (false === $output) {
            throw new \RuntimeException('Impossibile generare il file CSV.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Data', 'Inizio', 'Fine', 'Commessa', 'Attività', 'Persona', 'Descrizione', 'Durata minuti', 'Fatturabile', 'Costo storico euro'], ';', '"', '');
        foreach ($report->timeEntries as $entry) {
            fputcsv($output, [
                $entry->startedAt->format('d/m/Y'),
                $entry->startedAt->format('H:i'),
                $entry->endedAt?->format('H:i') ?? 'in corso',
                $entry->projectCode.' · '.$entry->projectName,
                $entry->activityTitle,
                $entry->userName,
                $entry->description,
                $entry->durationMinutes,
                $entry->billable ? 'Sì' : 'No',
                number_format($entry->costCents / 100, 2, ',', '.'),
            ], ';', '"', '');
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        if (false === $content) {
            throw new \RuntimeException('Impossibile leggere il file CSV generato.');
        }

        return $this->csvResponse($content, $filename);
    }

    #[Route('/utenti/csv', name: 'app_monthly_report_users_csv', methods: ['GET'])]
    public function usersCsv(
        Request $request,
        MonthlyReportService $reportService,
        ProjectRepository $projectRepository,
    ): Response {
        $month = $this->month($request);
        $project = $this->project($request, $projectRepository);
        $report = $reportService->build($month, $project?->getId());
        $filename = sprintf('report-mensile-utenti-%s%s.csv', $month->format('Y-m'), $project ? '-'.$project->getCode() : '');
        $output = fopen('php://temp', 'w+b');
        if (false === $output) {
            throw new \RuntimeException('Impossibile generare il file CSV.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Utente', 'Stato utente', 'Registrazioni concluse', 'Ore registrate', 'Minuti registrati', 'Tariffa standard euro/ora', 'Costo standard teorico euro', 'Costo storico effettivo euro', 'Scostamento euro'], ';', '"', '');
        foreach ($report->userCosts as $row) {
            fputcsv($output, [
                $row->userName,
                $row->active ? 'Attivo' : 'Disattivato',
                $row->timeEntryCount,
                sprintf('%d:%02d', intdiv($row->workedMinutes, 60), $row->workedMinutes % 60),
                $row->workedMinutes,
                $row->hasStandardRate() ? number_format($row->standardHourlyRateCents / 100, 2, ',', '.') : '',
                null === $row->standardCostCents ? '' : number_format($row->standardCostCents / 100, 2, ',', '.'),
                number_format($row->historicalCostCents / 100, 2, ',', '.'),
                null === $row->varianceCents ? '' : number_format($row->varianceCents / 100, 2, ',', '.'),
            ], ';', '"', '');
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        if (false === $content) {
            throw new \RuntimeException('Impossibile leggere il file CSV generato.');
        }

        return $this->csvResponse($content, $filename);
    }

    private function csvResponse(string $content, string $filename): Response
    {
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename));

        return $response;
    }

    private function month(Request $request): DateTimeImmutable
    {
        $value = trim((string) $request->query->get('month', ''));
        if ('' !== $value) {
            $month = DateTimeImmutable::createFromFormat('!Y-m', $value);
            if (false !== $month && $month->format('Y-m') === $value) {
                return $month;
            }
        }

        return new DateTimeImmutable('first day of this month midnight');
    }

    private function project(Request $request, ProjectRepository $repository): ?Project
    {
        $normalized = trim((string) $request->query->get('project', ''));
        if (!ctype_digit($normalized) || (int) $normalized <= 0) {
            return null;
        }
        $project = $repository->find((int) $normalized);

        return $project instanceof Project && !$project->isArchived() ? $project : null;
    }
}
