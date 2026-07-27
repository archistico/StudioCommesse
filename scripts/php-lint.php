<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directories = ['bin', 'config', 'migrations', 'public', 'scripts', 'src', 'tests'];
$excludedParts = [DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR];
$errors = [];
$count = 0;

foreach ($directories as $directory) {
    $path = $root.DIRECTORY_SEPARATOR.$directory;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || 'php' !== strtolower($file->getExtension())) {
            continue;
        }

        $filePath = $file->getPathname();
        foreach ($excludedParts as $excludedPart) {
            if (str_contains($filePath, $excludedPart)) {
                continue 2;
            }
        }

        ++$count;
        $command = sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($filePath));
        exec($command, $output, $exitCode);
        if (0 !== $exitCode) {
            $errors[] = implode(PHP_EOL, $output);
        }
        $output = [];
    }
}

if ([] !== $errors) {
    fwrite(STDERR, implode(PHP_EOL.PHP_EOL, $errors).PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf("Lint PHP superato: %d file.\n", $count));
