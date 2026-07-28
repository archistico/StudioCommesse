<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\AttachmentValidationException;
use App\Service\AttachmentStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AttachmentStorageTest extends TestCase
{
    private string $storageDirectory;
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $this->storageDirectory = sys_get_temp_dir().'/studio-commesse-storage-'.$suffix;
        $this->temporaryDirectory = sys_get_temp_dir().'/studio-commesse-upload-'.$suffix;
        mkdir($this->temporaryDirectory, 0700, true);
    }

    public function testStoresPdfOutsidePublicWithRandomKeyAndChecksum(): void
    {
        $source = $this->temporaryDirectory.'/preventivo.pdf';
        file_put_contents($source, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n");
        $storage = new AttachmentStorage($this->storageDirectory);

        $stored = $storage->store(new UploadedFile($source, 'Preventivo Cliente.pdf', 'application/pdf', null, true));

        self::assertSame('Preventivo Cliente.pdf', $stored->originalName);
        self::assertSame('application/pdf', $stored->mimeType);
        self::assertMatchesRegularExpression('#^\d{4}/\d{2}/[a-f0-9]{32}\.pdf$#', $stored->storageKey);
        self::assertSame(hash_file('sha256', $source), $stored->sha256);
        self::assertFileExists($storage->resolve($stored->storageKey));
        self::assertStringNotContainsString('/public/', str_replace('\\', '/', $storage->resolve($stored->storageKey)));
    }

    public function testRejectsExecutableAndEicarContent(): void
    {
        $storage = new AttachmentStorage($this->storageDirectory);
        $executable = $this->temporaryDirectory.'/manuale.pdf';
        file_put_contents($executable, "MZ\x90\x00fake executable");

        try {
            $storage->store(new UploadedFile($executable, 'manuale.pdf', 'application/pdf', null, true));
            self::fail('Un file eseguibile mascherato deve essere rifiutato.');
        } catch (AttachmentValidationException $exception) {
            self::assertStringContainsString('contenuto', mb_strtolower($exception->getMessage()));
        }

        $eicar = $this->temporaryDirectory.'/nota.txt';
        file_put_contents($eicar, "Fixture innocua: EICAR-STANDARD-ANTIVIRUS-TEST-\nFILE");

        $this->expectException(AttachmentValidationException::class);
        $this->expectExceptionMessage('controllo di sicurezza');
        $storage->store(new UploadedFile($eicar, 'nota.txt', 'text/plain', null, true));
    }

    public function testRejectsUnsupportedExtensionAndOversizedFile(): void
    {
        $storage = new AttachmentStorage($this->storageDirectory);
        $php = $this->temporaryDirectory.'/script.php';
        file_put_contents($php, '<?php echo 1;');

        try {
            $storage->store(new UploadedFile($php, 'script.php', 'text/x-php', null, true));
            self::fail('L’estensione PHP deve essere rifiutata.');
        } catch (AttachmentValidationException $exception) {
            self::assertStringContainsString('Tipo di file non consentito', $exception->getMessage());
        }

        $large = $this->temporaryDirectory.'/large.txt';
        $handle = fopen($large, 'wb');
        self::assertIsResource($handle);
        fseek($handle, AttachmentStorage::MAX_SIZE_BYTES);
        fwrite($handle, 'x');
        fclose($handle);

        $this->expectException(AttachmentValidationException::class);
        $this->expectExceptionMessage('10 MiB');
        $storage->store(new UploadedFile($large, 'large.txt', 'text/plain', null, true));
    }


    public function testQuarantineCanBeRestoredOrPurgedWithoutLeavingActiveFiles(): void
    {
        $source = $this->temporaryDirectory.'/documento.pdf';
        file_put_contents($source, "%PDF-1.4
%%EOF
");
        $storage = new AttachmentStorage($this->storageDirectory);
        $stored = $storage->store(new UploadedFile($source, 'documento.pdf', 'application/pdf', null, true));
        $activePath = $storage->resolve($stored->storageKey);

        $quarantined = $storage->quarantine($stored->storageKey);
        self::assertNotNull($quarantined);
        self::assertFileDoesNotExist($activePath);
        self::assertFileExists($quarantined->quarantinePath);

        $storage->restore($quarantined);
        self::assertFileExists($activePath);
        self::assertFileDoesNotExist($quarantined->quarantinePath);

        $quarantinedAgain = $storage->quarantine($stored->storageKey);
        self::assertNotNull($quarantinedAgain);
        $storage->purge($quarantinedAgain);
        self::assertFileDoesNotExist($activePath);
        self::assertFileDoesNotExist($quarantinedAgain->quarantinePath);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->storageDirectory);
        $this->removeDirectory($this->temporaryDirectory);
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
