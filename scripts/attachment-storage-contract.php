<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (!extension_loaded('fileinfo')) {
    fwrite(STDERR, "Estensione PHP richiesta non disponibile: fileinfo\n");
    exit(1);
}

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
