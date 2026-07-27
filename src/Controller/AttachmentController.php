<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Attachment;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AttachmentClassification;
use App\Enum\AuditAction;
use App\Exception\AttachmentValidationException;
use App\Form\AttachmentMetadataType;
use App\Form\AttachmentUploadType;
use App\Model\AttachmentUpload;
use App\Repository\ActivityRepository;
use App\Repository\AttachmentRepository;
use App\Repository\ProjectRepository;
use App\Security\Voter\AttachmentVoter;
use App\Service\AttachmentManager;
use App\Service\AttachmentStorage;
use App\Service\AuditLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_COLLABORATOR')]
final class AttachmentController extends AbstractController
{
    #[Route('/documenti', name: 'app_attachment_index', methods: ['GET'])]
    public function index(
        Request $request,
        AttachmentRepository $attachmentRepository,
        ProjectRepository $projectRepository,
        ActivityRepository $activityRepository,
    ): Response {
        $project = $this->findProject($request, $projectRepository);
        $activity = $this->findActivity($request, $activityRepository, $project);
        $classificationValue = trim((string) $request->query->get('classification', ''));
        $classification = AttachmentClassification::tryFrom($classificationValue);
        $query = trim((string) $request->query->get('q', ''));

        return $this->render('attachment/index.html.twig', [
            'attachments' => $attachmentRepository->findFiltered($project, $activity, $classification, $query),
            'projects' => $projectRepository->findAllForTimeReporting(),
            'activities' => $activityRepository->findAllForTimeReporting($project),
            'classifications' => AttachmentClassification::cases(),
            'selected_project' => $project,
            'selected_activity' => $activity,
            'selected_classification' => $classification,
            'query' => $query,
        ]);
    }

    #[Route('/commesse/{id}/documenti', name: 'app_attachment_project', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function project(
        Project $project,
        Request $request,
        AttachmentRepository $attachmentRepository,
        ActivityRepository $activityRepository,
        AttachmentManager $attachmentManager,
        AuditLogger $auditLogger,
    ): Response {
        $activities = $activityRepository->findForProject($project);
        $selectedActivity = $this->activityFromList($request->query->get('activity'), $activities);
        $classificationValue = trim((string) $request->query->get('classification', ''));
        $classification = AttachmentClassification::tryFrom($classificationValue);
        $query = trim((string) $request->query->get('q', ''));
        $upload = new AttachmentUpload();
        $upload->activity = $selectedActivity;
        $form = $this->createForm(AttachmentUploadType::class, $upload, ['activity_choices' => $activities]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $attachment = $attachmentManager->create($project, $this->requireCurrentUser(), $upload);
                $auditLogger->log(
                    AuditAction::AttachmentUploaded,
                    $this->requireCurrentUser()->getUserIdentifier(),
                    Attachment::class,
                    $attachment->getId(),
                    [
                        'project_id' => $project->getId(),
                        'activity_id' => $attachment->getActivity()?->getId(),
                        'classification' => $attachment->getClassification()->value,
                        'original_name' => $attachment->getOriginalName(),
                        'size_bytes' => $attachment->getSizeBytes(),
                        'sha256' => $attachment->getSha256(),
                    ],
                    $request->getClientIp(),
                );
                $this->addFlash('success', 'Documento caricato correttamente.');

                return $this->redirectToRoute('app_attachment_project', ['id' => $project->getId()]);
            } catch (AttachmentValidationException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('attachment/project.html.twig', [
            'project' => $project,
            'attachments' => $attachmentRepository->findFiltered($project, $selectedActivity, $classification, $query),
            'activities' => $activities,
            'classifications' => AttachmentClassification::cases(),
            'selected_activity' => $selectedActivity,
            'selected_classification' => $classification,
            'query' => $query,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/documenti/{id}', name: 'app_attachment_show', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function show(
        Attachment $attachment,
        Request $request,
        ActivityRepository $activityRepository,
        AttachmentManager $attachmentManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(AttachmentVoter::VIEW, $attachment);
        $canManage = $this->isGranted(AttachmentVoter::MANAGE, $attachment);
        $form = null;

        if ($canManage) {
            $previous = [
                'classification' => $attachment->getClassification()->value,
                'activity_id' => $attachment->getActivity()?->getId(),
                'description' => $attachment->getDescription(),
            ];
            $form = $this->createForm(AttachmentMetadataType::class, $attachment, [
                'activity_choices' => $activityRepository->findForProject($attachment->getProject()),
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $attachmentManager->updateMetadata($attachment);
                    $auditLogger->log(
                        AuditAction::AttachmentUpdated,
                        $this->requireCurrentUser()->getUserIdentifier(),
                        Attachment::class,
                        $attachment->getId(),
                        [
                            'previous_classification' => $previous['classification'],
                            'classification' => $attachment->getClassification()->value,
                            'previous_activity_id' => $previous['activity_id'],
                            'activity_id' => $attachment->getActivity()?->getId(),
                        ],
                        $request->getClientIp(),
                    );
                    $this->addFlash('success', 'Dati del documento aggiornati.');

                    return $this->redirectToRoute('app_attachment_show', ['id' => $attachment->getId()]);
                } catch (AttachmentValidationException $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        } elseif ($request->isMethod('POST')) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('attachment/show.html.twig', [
            'attachment' => $attachment,
            'can_manage' => $canManage,
            'form' => $form,
        ], new Response(status: null !== $form && $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/documenti/{id}/scarica', name: 'app_attachment_download', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function download(
        Attachment $attachment,
        Request $request,
        AttachmentStorage $storage,
        AuditLogger $auditLogger,
    ): BinaryFileResponse {
        $this->denyAccessUnlessGranted(AttachmentVoter::VIEW, $attachment);
        $path = $storage->resolve($attachment->getStorageKey());
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment->getOriginalName());
        $response->headers->set('Content-Type', $attachment->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $auditLogger->log(
            AuditAction::AttachmentDownloaded,
            $this->requireCurrentUser()->getUserIdentifier(),
            Attachment::class,
            $attachment->getId(),
            ['project_id' => $attachment->getProject()->getId(), 'original_name' => $attachment->getOriginalName()],
            $request->getClientIp(),
        );

        return $response;
    }

    #[Route('/documenti/{id}/elimina', name: 'app_attachment_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(
        Attachment $attachment,
        Request $request,
        AttachmentManager $attachmentManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(AttachmentVoter::MANAGE, $attachment);
        if (!$this->isCsrfTokenValid('delete_attachment_'.$attachment->getId(), (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Token CSRF non valido.');
        }

        $id = $attachment->getId();
        $projectId = $attachment->getProject()->getId();
        $name = $attachment->getOriginalName();
        $attachmentManager->delete($attachment);
        $auditLogger->log(
            AuditAction::AttachmentDeleted,
            $this->requireCurrentUser()->getUserIdentifier(),
            Attachment::class,
            $id,
            ['project_id' => $projectId, 'original_name' => $name],
            $request->getClientIp(),
        );
        $this->addFlash('success', 'Documento eliminato.');

        return $this->redirectToRoute('app_attachment_project', ['id' => $projectId]);
    }

    private function findProject(Request $request, ProjectRepository $repository): ?Project
    {
        $id = $this->positiveInt($request->query->get('project'));
        if (null === $id) {
            return null;
        }
        $project = $repository->find($id);

        return $project instanceof Project ? $project : null;
    }

    private function findActivity(Request $request, ActivityRepository $repository, ?Project $project): ?Activity
    {
        $id = $this->positiveInt($request->query->get('activity'));
        if (null === $id) {
            return null;
        }
        $activity = $repository->find($id);
        if (!$activity instanceof Activity) {
            return null;
        }
        if (null !== $project && $activity->getProject()?->getId() !== $project->getId()) {
            return null;
        }

        return $activity;
    }

    /** @param list<Activity> $activities */
    private function activityFromList(mixed $value, array $activities): ?Activity
    {
        $id = $this->positiveInt($value);
        if (null === $id) {
            return null;
        }
        foreach ($activities as $activity) {
            if ($activity->getId() === $id) {
                return $activity;
            }
        }

        return null;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }
        $normalized = (string) $value;

        return ctype_digit($normalized) && (int) $normalized > 0 ? (int) $normalized : null;
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
