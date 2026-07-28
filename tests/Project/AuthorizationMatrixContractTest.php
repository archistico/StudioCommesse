<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class AuthorizationMatrixContractTest extends TestCase
{
    public function testEveryNamedApplicationRouteHasAnExplicitPolicyAndMatchingMethods(): void
    {
        $root = dirname(__DIR__, 2);
        $matrix = require $root.'/config/authorization_matrix.php';
        if (!is_array($matrix)) {
            self::fail('Matrice autorizzazioni non valida.');
        }
        /** @var array<string, array{methods: list<string>, access: string, ownership: string, archive: string, data: string}> $matrix */

        /** @var array<string, list<string>> $routes */
        $routes = [];
        foreach (glob($root.'/src/Controller/*.php') ?: [] as $controller) {
            $source = (string) file_get_contents($controller);
            preg_match_all('/#\[Route\((.*?)\)\]/s', $source, $attributes);
            foreach ($attributes[1] as $attribute) {
                if (1 !== preg_match("/name:\s*'([^']+)'/", $attribute, $nameMatch)) {
                    continue;
                }
                self::assertSame(1, preg_match('/methods:\s*\[([^\]]+)\]/', $attribute, $methodsMatch), $nameMatch[1]);
                preg_match_all("/'([A-Z]+)'/", $methodsMatch[1], $methodMatches);
                $methods = array_values(array_unique($methodMatches[1]));
                sort($methods);
                $routes[$nameMatch[1]] = $methods;
            }
        }

        ksort($routes);
        $matrixRoutes = array_keys($matrix);
        sort($matrixRoutes);

        self::assertCount(48, $routes);
        self::assertSame(array_keys($routes), $matrixRoutes);
        foreach ($routes as $route => $methods) {
            self::assertArrayHasKey($route, $matrix);
            $matrixMethods = $matrix[$route]['methods'] ?? null;
            self::assertIsArray($matrixMethods, $route);
            sort($matrixMethods);
            self::assertSame($methods, $matrixMethods, $route);
        }
    }

    public function testSensitiveRoutesAreClassifiedAsPartnerOnly(): void
    {
        /** @var array<string, array{access: string}> $matrix */
        $matrix = require dirname(__DIR__, 2).'/config/authorization_matrix.php';

        foreach ([
            'app_user_index',
            'app_user_new',
            'app_user_edit',
            'app_economics_index',
            'app_payment_new',
            'app_payment_edit',
            'app_payment_delete',
            'app_control_index',
            'app_control_collaborator_show',
            'app_monthly_report',
            'app_monthly_report_csv',
            'app_monthly_report_users_csv',
            'app_audit_index',
            'app_audit_csv',
        ] as $route) {
            self::assertSame('partner', $matrix[$route]['access'], $route);
        }
    }

    public function testEveryWriteConnectedToAProjectDefinesArchiveBehaviour(): void
    {
        /** @var array<string, array{methods: list<string>, archive: string}> $matrix */
        $matrix = require dirname(__DIR__, 2).'/config/authorization_matrix.php';

        foreach ($matrix as $route => $policy) {
            if ([] === array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $policy['methods'])) {
                continue;
            }
            if (in_array($route, ['app_login', 'app_logout', 'app_user_new', 'app_user_edit', 'app_client_new', 'app_client_archive', 'app_client_restore', 'app_project_new', 'app_project_archive', 'app_project_restore'], true)) {
                continue;
            }

            self::assertSame('deny_write', $policy['archive'], $route);
        }
    }
}
