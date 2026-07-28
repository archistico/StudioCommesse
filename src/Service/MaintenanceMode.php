<?php

declare(strict_types=1);

namespace App\Service;

final readonly class MaintenanceMode
{
    public function __construct(private string $markerFile)
    {
    }

    public function enable(string $message): void
    {
        if ($this->isEnabled()) {
            throw new \RuntimeException('La modalità manutenzione risulta già attiva.');
        }

        $directory = dirname($this->markerFile);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossibile creare la directory della modalità manutenzione.');
        }

        $payload = json_encode([
            'enabledAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'message' => $message,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $temporary = $this->markerFile.'.tmp-'.bin2hex(random_bytes(6));
        if (false === file_put_contents($temporary, $payload."\n", LOCK_EX)) {
            throw new \RuntimeException('Impossibile attivare la modalità manutenzione.');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, $this->markerFile)) {
            @unlink($temporary);
            throw new \RuntimeException('Impossibile attivare la modalità manutenzione.');
        }
    }

    public function disable(): void
    {
        if (is_file($this->markerFile) && !unlink($this->markerFile)) {
            throw new \RuntimeException('Impossibile disattivare la modalità manutenzione.');
        }
    }

    /** @phpstan-impure */
    public function isEnabled(): bool
    {
        return is_file($this->markerFile);
    }

    public function message(): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        $content = file_get_contents($this->markerFile);
        if (!is_string($content)) {
            return 'Ripristino in corso.';
        }

        try {
            $decoded = json_decode($content, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return 'Ripristino in corso.';
        }

        return is_array($decoded) && is_string($decoded['message'] ?? null)
            ? $decoded['message']
            : 'Ripristino in corso.';
    }
}
