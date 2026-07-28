<?php

declare(strict_types=1);

require_once __DIR__.'/php-runtime-contract.php';

if (!studioCommessePhpRuntimeContract(false)) {
    exit(1);
}

$root = dirname(__DIR__);
$directory = $root.'/var/storage/attachments';
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    fwrite(STDERR, "Impossibile creare var/storage/attachments.\n");
    exit(1);
}

$probe = $directory.'/.write-test-'.bin2hex(random_bytes(8));
if (false === file_put_contents($probe, 'ok')) {
    fwrite(STDERR, "Lo spazio documentale non è scrivibile.\n");
    exit(1);
}
unlink($probe);

echo "Spazio documentale disponibile.\n";
