<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AttachmentValidationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class AttachmentStorage
{
    public const MAX_SIZE_BYTES = 10_485_760;

    /** @var array<string, list<string>> */
    private const ALLOWED_MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ];

    public function __construct(
        #[Autowire('%app.attachment_storage_dir%')]
        private string $storageDirectory,
    ) {
    }

    public function store(UploadedFile $file): StoredAttachment
    {
        if (!$file->isValid()) {
            throw new AttachmentValidationException('Il caricamento del file non è riuscito.');
        }

        $size = $file->getSize();
        if (!is_int($size) || $size <= 0) {
            throw new AttachmentValidationException('Il file è vuoto.');
        }
        if ($size > self::MAX_SIZE_BYTES) {
            throw new AttachmentValidationException('Il file supera il limite di 10 MiB.');
        }

        $originalName = $this->normalizeOriginalName($file->getClientOriginalName());
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!array_key_exists($extension, self::ALLOWED_MIME_TYPES)) {
            throw new AttachmentValidationException('Tipo di file non consentito. Sono ammessi PDF, immagini, TXT, CSV, DOCX e XLSX.');
        }

        $source = $file->getPathname();
        if (!is_file($source) || !is_readable($source)) {
            throw new AttachmentValidationException('Il file temporaneo non è leggibile.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = @$finfo->file($source);
        if (!is_string($mimeType)) {
            throw new AttachmentValidationException('Il file è stato rifiutato perché non ha superato il controllo di sicurezza.');
        }
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES[$extension], true)) {
            throw new AttachmentValidationException(sprintf('Il contenuto del file non corrisponde all’estensione .%s.', $extension));
        }

        $this->validateSignature($source, $extension);
        $sha256 = hash_file('sha256', $source);
        if (!is_string($sha256)) {
            throw new AttachmentValidationException('Impossibile calcolare l’impronta del file.');
        }

        $now = new \DateTimeImmutable();
        $relativeDirectory = $now->format('Y/m');
        $targetDirectory = $this->storageDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        $this->ensureDirectory($targetDirectory);
        $storageKey = $relativeDirectory.'/'.bin2hex(random_bytes(16)).'.'.$extension;
        $target = $this->absolutePath($storageKey);

        if (!copy($source, $target)) {
            throw new AttachmentValidationException('Impossibile salvare il file nello spazio documentale.');
        }
        @chmod($target, 0600);

        return new StoredAttachment($originalName, $storageKey, $mimeType, $size, $sha256);
    }

    public function resolve(string $storageKey): string
    {
        $path = $this->absolutePath($storageKey);
        if (!is_file($path) || !is_readable($path)) {
            throw new AttachmentValidationException('Il file richiesto non è disponibile nello spazio documentale.');
        }

        return $path;
    }

    public function delete(string $storageKey): void
    {
        $path = $this->absolutePath($storageKey);
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException('Impossibile eliminare il file dallo spazio documentale.');
        }
    }

    public function quarantine(string $storageKey): ?QuarantinedAttachment
    {
        $originalPath = $this->absolutePath($storageKey);
        if (!is_file($originalPath)) {
            return null;
        }

        $trashDirectory = dirname(rtrim($this->storageDirectory, '/\\')).DIRECTORY_SEPARATOR.'attachment-trash';
        $this->ensureDirectory($trashDirectory);
        $quarantinePath = $trashDirectory.DIRECTORY_SEPARATOR.bin2hex(random_bytes(16)).'.pending-delete';

        if (!rename($originalPath, $quarantinePath)) {
            throw new \RuntimeException('Impossibile mettere in sicurezza il file prima dell’eliminazione.');
        }

        return new QuarantinedAttachment($storageKey, $originalPath, $quarantinePath);
    }

    public function restore(QuarantinedAttachment $quarantined): void
    {
        $this->ensureDirectory(dirname($quarantined->originalPath));
        if (is_file($quarantined->quarantinePath) && !rename($quarantined->quarantinePath, $quarantined->originalPath)) {
            throw new \RuntimeException('Impossibile ripristinare il file dopo l’annullamento dell’operazione.');
        }
    }

    public function purge(QuarantinedAttachment $quarantined): void
    {
        if (is_file($quarantined->quarantinePath) && !unlink($quarantined->quarantinePath)) {
            throw new \RuntimeException('Impossibile eliminare definitivamente il file in quarantena.');
        }
    }

    private function normalizeOriginalName(string $name): string
    {
        $name = trim(str_replace(["\0", "\r", "\n"], '', basename(str_replace('\\', '/', $name))));
        if ('' === $name) {
            throw new AttachmentValidationException('Il nome del file non è valido.');
        }

        return mb_substr($name, 0, 255);
    }

    private function absolutePath(string $storageKey): string
    {
        if (1 !== preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}\.[a-z0-9]+$#', $storageKey)) {
            throw new AttachmentValidationException('Riferimento del file non valido.');
        }

        return rtrim($this->storageDirectory, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new AttachmentValidationException('Impossibile creare lo spazio documentale.');
        }
        if (!is_writable($directory)) {
            throw new AttachmentValidationException('Lo spazio documentale non è scrivibile.');
        }
    }

    private function validateSignature(string $path, string $extension): void
    {
        $handle = fopen($path, 'rb');
        if (false === $handle) {
            throw new AttachmentValidationException('Impossibile verificare il contenuto del file.');
        }
        $header = fread($handle, 8192);
        fclose($handle);
        if (!is_string($header)) {
            throw new AttachmentValidationException('Impossibile verificare il contenuto del file.');
        }

        if (str_contains($header, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')
            || 1 === preg_match('/EICAR-STANDARD-ANTIVIRUS-TEST-\s+FILE/', $header)
        ) {
            throw new AttachmentValidationException('Il file è stato rifiutato dal controllo di sicurezza.');
        }
        if (str_starts_with($header, 'MZ') || str_starts_with($header, "\x7fELF")) {
            throw new AttachmentValidationException('I file eseguibili non sono consentiti.');
        }

        $valid = match ($extension) {
            'pdf' => str_starts_with($header, '%PDF-'),
            'png' => str_starts_with($header, "\x89PNG\r\n\x1a\n"),
            'jpg', 'jpeg' => str_starts_with($header, "\xff\xd8\xff"),
            'webp' => str_starts_with($header, 'RIFF') && 'WEBP' === substr($header, 8, 4),
            'docx', 'xlsx' => str_starts_with($header, "PK\x03\x04"),
            'txt', 'csv' => !str_contains($header, "\0"),
            default => false,
        };

        if (!$valid) {
            throw new AttachmentValidationException('La firma del file non è coerente con il formato dichiarato.');
        }
    }
}
