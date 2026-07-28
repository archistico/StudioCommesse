<?php

declare(strict_types=1);

/** @return list<string> */
function studioCommesseRequiredPhpExtensions(): array
{
    return ['ctype', 'fileinfo', 'iconv', 'mbstring', 'pdo', 'pdo_sqlite'];
}

function studioCommessePhpRuntimeContract(bool $printSuccess = true): bool
{
    $errors = [];
    if (PHP_VERSION_ID < 80400) {
        $errors[] = sprintf('È richiesto PHP 8.4 o successivo; versione rilevata: %s.', PHP_VERSION);
    }

    $missing = array_values(array_filter(
        studioCommesseRequiredPhpExtensions(),
        static fn (string $extension): bool => !extension_loaded($extension),
    ));
    if ([] !== $missing) {
        $errors[] = 'Estensioni PHP mancanti: '.implode(', ', $missing).'.';
    }

    if ([] === $errors) {
        if ($printSuccess) {
            echo sprintf(
                "Runtime PHP disponibile: %s; estensioni richieste presenti (%s).\n",
                PHP_VERSION,
                implode(', ', studioCommesseRequiredPhpExtensions()),
            );
        }

        return true;
    }

    $loadedIni = php_ini_loaded_file();
    $iniPath = is_string($loadedIni) && '' !== trim($loadedIni) ? $loadedIni : '(nessun php.ini caricato)';
    $extensionDirectory = (string) ini_get('extension_dir');

    fwrite(STDERR, "Runtime PHP non conforme ai requisiti di StudioCommesse.\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- '.$error."\n");
    }
    fwrite(STDERR, 'PHP CLI: '.PHP_BINARY."\n");
    fwrite(STDERR, 'php.ini caricato: '.$iniPath."\n");
    fwrite(STDERR, 'extension_dir: '.('' !== trim($extensionDirectory) ? $extensionDirectory : '(non configurata)')."\n");

    if (PHP_OS_FAMILY === 'Windows' && in_array('fileinfo', $missing, true)) {
        $resolvedExtensionDirectory = $extensionDirectory;
        if ('' !== trim($resolvedExtensionDirectory)
            && 1 !== preg_match('~^(?:[A-Za-z]:[\\/]|[\\/]{2})~', $resolvedExtensionDirectory)
        ) {
            $resolvedExtensionDirectory = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.$resolvedExtensionDirectory;
        }
        $fileinfoDll = rtrim($resolvedExtensionDirectory, '/\\').DIRECTORY_SEPARATOR.'php_fileinfo.dll';
        fwrite(STDERR, 'DLL fileinfo attesa: '.$fileinfoDll.' ('.(is_file($fileinfoDll) ? 'presente' : 'non trovata').")\n");
        fwrite(STDERR, "Nel php.ini usato dalla CLI abilitare: extension=fileinfo\n");
        fwrite(STDERR, "Poi chiudere e riaprire il terminale e verificare con:\n");
        fwrite(STDERR, "  php --ini\n");
        fwrite(STDERR, "  php -m | Select-String -Pattern '^fileinfo$'\n");
    } else {
        fwrite(STDERR, "Correggere il php.ini o installare/abilitare le estensioni mancanti, quindi rieseguire il comando.\n");
    }

    return false;
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(studioCommessePhpRuntimeContract() ? 0 : 1);
}
