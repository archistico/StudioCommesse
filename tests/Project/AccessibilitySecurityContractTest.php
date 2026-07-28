<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class AccessibilitySecurityContractTest extends TestCase
{
    public function testLoginThrottlingAuditPrivacyAndSecurityHeadersAreConfigured(): void
    {
        $root = dirname(__DIR__, 2);
        $security = (string) file_get_contents($root.'/config/packages/security.yaml');
        $services = (string) file_get_contents($root.'/config/services.yaml');
        $subscriber = (string) file_get_contents($root.'/src/EventSubscriber/SecurityAuditSubscriber.php');
        $headers = (string) file_get_contents($root.'/src/EventSubscriber/SecurityHeadersSubscriber.php');
        $privacy = (string) file_get_contents($root.'/src/Service/AuditPrivacyGuard.php');
        $logger = (string) file_get_contents($root.'/src/Service/AuditLogger.php');
        $monthlyReport = (string) file_get_contents($root.'/src/Service/MonthlyReportService.php');

        self::assertStringContainsString("app.login_max_attempts: 5", $services);
        self::assertStringContainsString("app.login_lockout_interval: '1 hour'", $services);
        self::assertStringContainsString("max_attempts: '%app.login_max_attempts%'", $security);
        self::assertStringContainsString("interval: '%app.login_lockout_interval%'", $security);
        self::assertStringContainsString("cache_pool: 'cache.rate_limiter'", $security);
        self::assertStringContainsString('TooManyLoginAttemptsAuthenticationException', $subscriber);
        self::assertStringContainsString('AuditAction::LoginThrottled', $subscriber);
        self::assertStringContainsString("'identifier_fingerprint'", $subscriber);
        self::assertStringNotContainsString("['reason' =>", $subscriber);
        self::assertStringContainsString("hash_hmac('sha256'", $privacy);
        self::assertStringContainsString("'detail_keys' => \$detailKeys", $privacy);
        self::assertStringNotContainsString("'detail_keys' => array_values(", $privacy);
        self::assertStringContainsString("AuditAction::LoginSucceeded, AuditAction::LoginFailed, AuditAction::LoginThrottled => 'Accesso applicativo'", $monthlyReport);
        self::assertStringContainsString('$this->privacyGuard->logContext($entry)', $logger);
        foreach (['Content-Security-Policy', 'X-Frame-Options', 'X-Content-Type-Options', 'Referrer-Policy', 'Permissions-Policy', 'Strict-Transport-Security'] as $header) {
            self::assertStringContainsString($header, $headers);
        }
    }

    public function testLayoutsAndTablesExposeTheAccessibilityContract(): void
    {
        $root = dirname(__DIR__, 2);
        $base = (string) file_get_contents($root.'/templates/base.html.twig');
        $layout = (string) file_get_contents($root.'/templates/layout/app.html.twig');
        $login = (string) file_get_contents($root.'/templates/authentication/login.html.twig');
        $flashes = (string) file_get_contents($root.'/templates/components/flash_messages.html.twig');
        $css = (string) file_get_contents($root.'/public/assets/css/app.css');

        self::assertStringContainsString('class="skip-link" href="#main-content"', $base);
        self::assertStringContainsString('aria-label="Navigazione principale"', $layout);
        self::assertStringContainsString('id="main-content" tabindex="-1"', $layout);
        self::assertStringContainsString('aria-current=', $layout);
        self::assertStringContainsString('aria-describedby="login-security-note"', $login);
        self::assertStringContainsString('value="{{ error ? \'\' : last_username }}"', $login);
        self::assertStringContainsString('aria-live="{{ is_error ? \'assertive\' : \'polite\' }}"', $flashes);
        self::assertStringContainsString(':focus-visible', $css);
        self::assertStringContainsString('prefers-reduced-motion: reduce', $css);

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/templates'));
        foreach ($iterator as $template) {
            if (!$template->isFile() || !str_ends_with($template->getFilename(), '.html.twig')) {
                continue;
            }
            $contents = (string) file_get_contents($template->getPathname());
            preg_match_all('/<th(?:\s|>)/', $contents, $allHeaders);
            preg_match_all('/<th\s+scope="col"(?:\s|>)/', $contents, $scopedHeaders);
            self::assertSame(count($allHeaders[0]), count($scopedHeaders[0]), $template->getPathname());
        }
    }

    public function testManualsContractsAndPackageAreAlignedToM92H(): void
    {
        $root = dirname(__DIR__, 2);
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');
        $packager = (string) file_get_contents($root.'/scripts/package-release.ps1');
        $verifier = (string) file_get_contents($root.'/scripts/verify-release-package.ps1');
        $services = (string) file_get_contents($root.'/config/services.yaml');
        $readme = (string) file_get_contents($root.'/README.md');

        foreach (['docs/USER_MANUAL.md', 'docs/ADMIN_MANUAL.md', 'docs/SECURITY.md', 'docs/ACCESSIBILITY.md', 'scripts/m92h-accessibility-security-contract.php'] as $file) {
            self::assertFileExists($root.'/'.$file);
            self::assertStringContainsString("'{$file}'", $packager, $file);
            self::assertStringContainsString("'{$file}'", $verifier, $file);
        }
        self::assertStringContainsString("app.version: '0.9.2-M9.2-H'", $services);
        self::assertStringContainsString('scripts/m92h-accessibility-security-contract.php', $validation);
        self::assertStringContainsString('M9.2-H VALIDATION PASSED', $validation);
        self::assertStringContainsString('StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip', $packager);
        self::assertLessThanOrEqual(100, substr_count($readme, "\n") + 1);
        self::assertDoesNotMatchRegularExpression('/\bM\d+(?:\.\d+)*(?:-[A-Z0-9.]+)?\b|baseline|candidate|VALIDATION PASSED/i', $readme);
    }
}
