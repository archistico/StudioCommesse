<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\AuditAction;
use App\Query\AuditSearchCriteria;
use App\Repository\AuditLogRepository;
use DateInterval;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/audit')]
#[IsGranted('ROLE_PARTNER')]
final class AuditController extends AbstractController
{
    #[Route('', name: 'app_audit_index', methods: ['GET'])]
    public function index(Request $request, AuditLogRepository $repository): Response
    {
        $criteria = $this->criteria($request);
        $groups = $this->groups();
        $page = $repository->findPage($criteria);

        return $this->render('audit/index.html.twig', [
            'audit_page' => $page,
            'audit_summary' => $repository->summarize($criteria),
            'criteria' => $criteria,
            'groups' => $groups,
            'actions' => AuditAction::cases(),
            'export_query' => $this->filterQuery($request),
            'pagination_query' => $this->filterQuery($request),
        ]);
    }

    #[Route('/csv', name: 'app_audit_csv', methods: ['GET'])]
    public function csv(Request $request, AuditLogRepository $repository): Response
    {
        $output = fopen('php://temp', 'w+b');
        if (false === $output) {
            throw new \RuntimeException('Impossibile aprire il flusso CSV.');
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Data e ora', 'Gruppo', 'Azione', 'Attore', 'Soggetto', 'IP', 'Request ID', 'Rotta', 'Metodo', 'Dettagli'], ';', '"', '', "\r\n");
        foreach ($repository->findForExport($this->criteria($request)) as $entry) {
            $details = json_encode($entry->getVisibleDetails(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            fputcsv($output, [
                $entry->getOccurredAt()->format('d/m/Y H:i:s'),
                $entry->getAction()->groupLabel(),
                $entry->getAction()->label(),
                $entry->getActorLabel(),
                $entry->getSubjectLabel(),
                $entry->getIpAddress() ?? '',
                $entry->getRequestId() ?? '',
                $entry->getRoute() ?? '',
                $entry->getHttpMethod() ?? '',
                false === $details ? '{}' : $details,
            ], ';', '"', '', "\r\n");
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        if (false === $content) {
            throw new \RuntimeException('Impossibile leggere il file CSV generato.');
        }

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            'studio-commesse-audit-'.date('Y-m-d-His').'.csv',
        ));
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function criteria(Request $request): AuditSearchCriteria
    {
        $actionValue = trim($request->query->getString('action'));
        $action = '' === $actionValue ? null : AuditAction::tryFrom($actionValue);
        $group = trim($request->query->getString('group'));
        if ('' === $group || !in_array($group, $this->groups(), true)) {
            $group = null;
        }
        $actor = trim($request->query->getString('actor'));
        $requestId = trim($request->query->getString('request_id'));
        if (1 !== preg_match('/^[A-Za-z0-9._-]{8,64}$/', $requestId)) {
            $requestId = null;
        }
        $from = $this->parseDate($request->query->getString('from'));
        $to = $this->parseDate($request->query->getString('to'));

        return new AuditSearchCriteria(
            group: $group,
            action: $action,
            actor: '' === $actor ? null : mb_substr($actor, 0, 120),
            requestId: $requestId,
            occurredFrom: $from,
            occurredBefore: $to?->add(new DateInterval('P1D')),
            page: max(1, $request->query->getInt('page', 1)),
            perPage: 50,
        );
    }

    /** @return list<string> */
    private function groups(): array
    {
        $groups = [];
        foreach (AuditAction::cases() as $action) {
            $groups[$action->groupLabel()] = true;
        }
        $labels = array_keys($groups);
        sort($labels, SORT_NATURAL | SORT_FLAG_CASE);

        return $labels;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $date : null;
    }

    /** @return array<string, string> */
    private function filterQuery(Request $request): array
    {
        $query = [];
        foreach (['group', 'action', 'actor', 'request_id', 'from', 'to'] as $name) {
            $value = trim($request->query->getString($name));
            if ('' !== $value) {
                $query[$name] = $value;
            }
        }

        return $query;
    }
}
