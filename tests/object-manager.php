<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;

require dirname(__DIR__).'/config/bootstrap.php';

$kernel = new Kernel('dev', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
