<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Service\MaintenanceMode;
use App\Tests\DatabaseWebTestCase;

final class MaintenanceModeTest extends DatabaseWebTestCase
{
    public function testMaintenanceModeBlocksRequestsWithServiceUnavailableResponse(): void
    {
        $maintenance = self::getContainer()->get(MaintenanceMode::class);
        self::assertInstanceOf(MaintenanceMode::class, $maintenance);
        $maintenance->enable('Ripristino di prova in corso.');

        try {
            $this->client->request('GET', '/login');
            self::assertResponseStatusCodeSame(503);
            self::assertResponseHeaderSame('Retry-After', '60');
            self::assertSelectorTextContains('body', 'Ripristino di prova in corso.');
        } finally {
            $maintenance->disable();
        }
    }
}
