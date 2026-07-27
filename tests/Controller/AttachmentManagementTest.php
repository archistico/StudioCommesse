<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Attachment;
use App\Enum\AttachmentClassification;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AttachmentManagementTest extends DatabaseWebTestCase
{
    public function testCollaboratorUploadsReadsAndDownloadsProtectedProjectDocument(): void
    {
        $responsible = $this->createUser('responsabile-documenti');
        $project = $this->createProject($this->createCustomer('Cliente Documenti'), $responsible);
        $activity = $this->createTestActivity($project, $responsible, 'Relazione tecnica');
        $this->client->loginUser($responsible);
        $file = $this->createPdf('relazione.pdf');

        $crawler = $this->client->request('GET', '/commesse/'.$project->getId().'/documenti?activity='.$activity->getId());
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Carica documento')->form([
            'attachment_upload[classification]' => AttachmentClassification::Technical->value,
            'attachment_upload[activity]' => (string) $activity->getId(),
            'attachment_upload[description]' => 'Relazione strutturale',
        ]);
        $form['attachment_upload[file]']->upload($file);
        $this->client->submit($form);

        self::assertResponseRedirects('/commesse/'.$project->getId().'/documenti');
        $attachment = $this->entityManager->getRepository(Attachment::class)->findOneBy(['project' => $project]);
        self::assertInstanceOf(Attachment::class, $attachment);
        self::assertSame($activity->getId(), $attachment->getActivity()?->getId());
        self::assertSame('relazione.pdf', $attachment->getOriginalName());
        self::assertSame('Relazione strutturale', $attachment->getDescription());

        $storageDirectory = (string) self::getContainer()->getParameter('app.attachment_storage_dir');
        $storedPath = $storageDirectory.'/'.str_replace('/', DIRECTORY_SEPARATOR, $attachment->getStorageKey());
        self::assertFileExists($storedPath);
        self::assertStringNotContainsString('/public/', str_replace('\\', '/', $storedPath));

        $this->client->request('GET', '/documenti/'.$attachment->getId().'/scarica');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('attachment;', (string) $this->client->getResponse()->headers->get('content-disposition'));
        self::assertSame('nosniff', $this->client->getResponse()->headers->get('x-content-type-options'));
    }

    public function testUnrelatedCollaboratorCanReadButCannotManageAnotherUsersDocument(): void
    {
        $owner = $this->createUser('proprietario-documento');
        $viewer = $this->createUser('lettore-documento');
        $project = $this->createProject($this->createCustomer('Cliente Lettura'), $owner);
        $attachment = $this->uploadDocument($project->getId(), $owner, 'verbale.pdf');

        $this->client->loginUser($viewer);
        $crawler = $this->client->request('GET', '/documenti/'.$attachment->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Puoi consultare e scaricare');
        self::assertCount(0, $crawler->selectButton('Salva modifiche'));
        self::assertCount(0, $crawler->selectButton('Elimina documento'));

        $this->client->request('POST', '/documenti/'.$attachment->getId().'/elimina', ['_token' => 'non-rilevante']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testUploaderCanEditMetadataAndDeleteOnlyFromDocumentPage(): void
    {
        $uploader = $this->createUser('autore-documento');
        $project = $this->createProject($this->createCustomer('Cliente Gestione'), $uploader);
        $activity = $this->createTestActivity($project, $uploader, 'Attività collegata');
        $attachment = $this->uploadDocument($project->getId(), $uploader, 'contratto.pdf');
        $this->client->loginUser($uploader);

        $crawler = $this->client->request('GET', '/documenti/'.$attachment->getId());
        $form = $crawler->selectButton('Salva modifiche')->form([
            'attachment_metadata[classification]' => AttachmentClassification::Contractual->value,
            'attachment_metadata[activity]' => (string) $activity->getId(),
            'attachment_metadata[description]' => 'Contratto firmato',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/documenti/'.$attachment->getId());

        $this->entityManager->clear();
        $attachment = $this->entityManager->find(Attachment::class, $attachment->getId());
        self::assertInstanceOf(Attachment::class, $attachment);
        self::assertSame(AttachmentClassification::Contractual, $attachment->getClassification());
        self::assertSame($activity->getId(), $attachment->getActivity()?->getId());

        $crawler = $this->client->request('GET', '/documenti/'.$attachment->getId());
        $delete = $crawler->selectButton('Elimina documento')->form();
        $this->client->submit($delete);
        self::assertResponseRedirects('/commesse/'.$project->getId().'/documenti');
        self::assertNull($this->entityManager->find(Attachment::class, $attachment->getId()));
    }

    public function testArchivedProjectRejectsCraftedUpload(): void
    {
        $partner = $this->createUser('socio-documenti', UserRole::Partner);
        $project = $this->createProject($this->createCustomer('Cliente Archivio'), $partner, status: ProjectStatus::Completed);
        $project->archive();
        $this->entityManager->flush();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/commesse/'.$project->getId().'/documenti');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->selectButton('Carica documento'));

        /** @var CsrfTokenManagerInterface $csrf */
        $csrf = self::getContainer()->get(CsrfTokenManagerInterface::class);
        $this->client->request('POST', '/commesse/'.$project->getId().'/documenti', [
            'attachment_upload' => [
                'classification' => AttachmentClassification::Technical->value,
                'activity' => '',
                'description' => '',
                '_token' => $csrf->getToken('attachment_upload')->getValue(),
            ],
        ], ['attachment_upload' => ['file' => new UploadedFile($this->createPdf('forzato.pdf'), 'forzato.pdf', 'application/pdf', null, true)]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager->getRepository(Attachment::class)->count([]));
    }

    private function uploadDocument(?int $projectId, User $user, string $name): Attachment
    {
        self::assertIsInt($projectId);
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/commesse/'.$projectId.'/documenti');
        $form = $crawler->selectButton('Carica documento')->form([
            'attachment_upload[classification]' => AttachmentClassification::Other->value,
            'attachment_upload[activity]' => '',
            'attachment_upload[description]' => '',
        ]);
        $form['attachment_upload[file]']->upload($this->createPdf($name));
        $this->client->submit($form);
        $attachment = $this->entityManager->getRepository(Attachment::class)->findOneBy(['originalName' => $name]);
        self::assertInstanceOf(Attachment::class, $attachment);

        return $attachment;
    }

    private function createPdf(string $name): string
    {
        $directory = sys_get_temp_dir().'/studio-commesse-tests';
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $path = $directory.'/'.bin2hex(random_bytes(6)).'-'.$name;
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");

        return $path;
    }

    protected function tearDown(): void
    {
        $storageDirectory = self::getContainer()->getParameter('app.attachment_storage_dir');
        if (is_string($storageDirectory)) {
            $this->removeDirectory($storageDirectory);
        }
        parent::tearDown();
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
