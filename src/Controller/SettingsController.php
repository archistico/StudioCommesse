<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AttachmentMutationLock;
use App\Service\BackupManager;
use App\Service\DatabaseDataResetter;
use App\Service\RequestRuntimeLock;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/impostazioni')]
#[IsGranted('ROLE_PARTNER')]
final class SettingsController extends AbstractController
{
    #[Route('', name: 'app_settings_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('settings/index.html.twig');
    }

    #[Route('/backup', name: 'app_settings_backup', methods: ['POST'])]
    public function backup(
        Request $request,
        BackupManager $backupManager,
        #[Autowire('%kernel.cache_dir%')] string $cacheDirectory,
    ): BinaryFileResponse {
        if (!$this->isCsrfTokenValid('database_backup', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF non valido.');
        }

        $directory = $cacheDirectory.'/database-downloads';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossibile preparare il download del database.');
        }
        $path = $directory.'/'.bin2hex(random_bytes(16)).'.sqlite';
        try {
            $backupManager->snapshotDatabase($path);
            $response = $this->file($path, 'studio-commesse-'.date('Y-m-d-His').'.sqlite');
            $response->headers->set('Content-Type', 'application/vnd.sqlite3');
            $response->headers->set('Cache-Control', 'no-store, private');

            return $response->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            if (is_file($path)) {
                unlink($path);
            }
            throw $exception;
        }
    }

    #[Route('/azzera-dati', name: 'app_settings_reset', methods: ['POST'])]
    public function reset(
        Request $request,
        DatabaseDataResetter $resetter,
        RequestRuntimeLock $runtimeLock,
        AttachmentMutationLock $attachmentLock,
    ): Response {
        if (!$this->isCsrfTokenValid('database_reset', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF non valido.');
        }
        if ('CANCELLA DATI' !== $request->request->getString('confirmation')) {
            return $this->render('settings/index.html.twig', [
                'reset_error' => 'Scrivi CANCELLA DATI per confermare lo svuotamento del database.',
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $lock = $runtimeLock->tryAcquireExclusiveForRequest($request);
        if (null === $lock) {
            return $this->render('settings/index.html.twig', [
                'reset_error' => 'Sono in corso altre operazioni. Riprova tra qualche istante.',
            ], new Response(status: Response::HTTP_SERVICE_UNAVAILABLE, headers: ['Retry-After' => '5']));
        }

        try {
            $attachments = $attachmentLock->acquireExclusive();
            try {
                $summary = $resetter->resetKeepingUsers();
            } finally {
                $attachments->release();
            }
        } finally {
            $lock->release();
        }

        $this->addFlash('success', sprintf('Database svuotato. %d utenti conservati.', $summary['users']));

        return $this->redirectToRoute('app_settings_index');
    }
}
