<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__).'/vendor/autoload.php';

$path = dirname(__DIR__).'/config/packages/doctrine.yaml';
$configuration = Yaml::parseFile($path);

if (!is_array($configuration)) {
    throw new RuntimeException('config/packages/doctrine.yaml non contiene una configurazione YAML valida.');
}

$doctrine = $configuration['doctrine'] ?? null;
if (!is_array($doctrine)) {
    throw new RuntimeException('La sezione doctrine è assente o non valida.');
}

$dbal = $doctrine['dbal'] ?? null;
$orm = $doctrine['orm'] ?? null;
if (!is_array($dbal) || !is_array($orm)) {
    throw new RuntimeException('Le sezioni doctrine.dbal e doctrine.orm sono obbligatorie.');
}

$obsoleteDbalKeys = ['use_savepoints'];
$obsoleteOrmKeys = [
    'auto_generate_proxy_classes',
    'proxy_dir',
    'proxy_namespace',
    'enable_lazy_ghost_objects',
];

foreach ($obsoleteDbalKeys as $key) {
    if (array_key_exists($key, $dbal)) {
        throw new RuntimeException(sprintf('Opzione Doctrine DBAL non supportata: doctrine.dbal.%s', $key));
    }
}

foreach ($obsoleteOrmKeys as $key) {
    if (array_key_exists($key, $orm)) {
        throw new RuntimeException(sprintf('Opzione Doctrine ORM non supportata: doctrine.orm.%s', $key));
    }
}

$production = $configuration['when@prod']['doctrine']['orm'] ?? [];
if (!is_array($production)) {
    throw new RuntimeException('La sezione when@prod.doctrine.orm non è valida.');
}

foreach ($obsoleteOrmKeys as $key) {
    if (array_key_exists($key, $production)) {
        throw new RuntimeException(sprintf('Opzione Doctrine ORM non supportata in produzione: when@prod.doctrine.orm.%s', $key));
    }
}

if (($dbal['url'] ?? null) !== '%env(resolve:DATABASE_URL)%') {
    throw new RuntimeException('doctrine.dbal.url deve usare DATABASE_URL.');
}

if (($dbal['profiling_collect_backtrace'] ?? null) !== false) {
    throw new RuntimeException('doctrine.dbal.profiling_collect_backtrace deve restare false per evitare backtrace costosi.');
}

$developmentDbal = $configuration['when@dev']['doctrine']['dbal'] ?? null;
if (!is_array($developmentDbal)
    || ($developmentDbal['logging'] ?? null) !== false
    || ($developmentDbal['profiling'] ?? null) !== false
) {
    throw new RuntimeException('In dev logging e profiling DBAL devono essere disattivati; usare lo script diagnostico dedicato.');
}

if (array_key_exists('schema_filter', $dbal)) {
    throw new RuntimeException(
        'doctrine.dbal.schema_filter non deve nascondere la tabella metadata ai comandi delle migrazioni.',
    );
}

if (($orm['auto_mapping'] ?? null) !== true) {
    throw new RuntimeException('doctrine.orm.auto_mapping deve essere true.');
}

fwrite(STDOUT, "Contratto configurazione DoctrineBundle 3.3 / ORM 3 verificato.\n");
