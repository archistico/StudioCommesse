<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'services' => (string) file_get_contents($root.'/config/services.yaml'),
    'security' => (string) file_get_contents($root.'/config/packages/security.yaml'),
    'framework' => (string) file_get_contents($root.'/config/packages/framework.yaml'),
    'audit_action' => (string) file_get_contents($root.'/src/Enum/AuditAction.php'),
    'security_audit' => (string) file_get_contents($root.'/src/EventSubscriber/SecurityAuditSubscriber.php'),
    'security_headers' => (string) file_get_contents($root.'/src/EventSubscriber/SecurityHeadersSubscriber.php'),
    'privacy' => (string) file_get_contents($root.'/src/Service/AuditPrivacyGuard.php'),
    'audit_logger' => (string) file_get_contents($root.'/src/Service/AuditLogger.php'),
    'monthly_report' => (string) file_get_contents($root.'/src/Service/MonthlyReportService.php'),
    'base' => (string) file_get_contents($root.'/templates/base.html.twig'),
    'layout' => (string) file_get_contents($root.'/templates/layout/app.html.twig'),
    'login' => (string) file_get_contents($root.'/templates/authentication/login.html.twig'),
    'css' => (string) file_get_contents($root.'/public/assets/css/app.css'),
    'validation' => (string) file_get_contents($root.'/scripts/validate.ps1'),
    'packager' => (string) file_get_contents($root.'/scripts/package-release.ps1'),
];

$checks = [
    [str_contains($files['services'], "app.version: '0.9.2-M9.2-H'"), 'versione M9.2-H'],
    [str_contains($files['services'], 'app.login_max_attempts: 5') && str_contains($files['services'], "app.login_lockout_interval: '1 hour'"), 'soglia login'],
    [str_contains($files['security'], "max_attempts: '%app.login_max_attempts%'") && str_contains($files['security'], "interval: '%app.login_lockout_interval%'") && str_contains($files['security'], "cache_pool: 'cache.rate_limiter'"), 'rate limiter Symfony'],
    [str_contains($files['framework'], 'cookie_httponly: true') && str_contains($files['framework'], 'cookie_samesite: strict'), 'cookie sessione'],
    [str_contains($files['audit_action'], "security.login_throttled") && str_contains($files['audit_action'], 'Accesso temporaneamente bloccato'), 'azione audit blocco'],
    [str_contains($files['security_audit'], 'TooManyLoginAttemptsAuthenticationException') && str_contains($files['security_audit'], 'AuditAction::LoginThrottled'), 'audit throttling'],
    [str_contains($files['security_audit'], "'identifier_fingerprint'") && !str_contains($files['security_audit'], "['reason' =>"), 'privacy tentativi falliti'],
    [str_contains($files['privacy'], "hash_hmac('sha256'") && str_contains($files['privacy'], "'detail_keys' => \$detailKeys") && !str_contains($files['privacy'], "'detail_keys' => array_values("), 'impronte log'],
    [str_contains($files['monthly_report'], "AuditAction::LoginSucceeded, AuditAction::LoginFailed, AuditAction::LoginThrottled => 'Accesso applicativo'"), 'classificazione report login bloccato'],
    [str_contains($files['audit_logger'], '$this->privacyGuard->logContext($entry)'), 'mirror minimizzato'],
    [str_contains($files['security_headers'], 'Content-Security-Policy') && str_contains($files['security_headers'], 'Strict-Transport-Security') && str_contains($files['security_headers'], "addCacheControlDirective('no-store')"), 'intestazioni sicurezza'],
    [str_contains($files['base'], 'class="skip-link" href="#main-content"'), 'skip link'],
    [str_contains($files['layout'], 'aria-label="Navigazione principale"') && str_contains($files['layout'], 'aria-current='), 'landmark navigazione'],
    [str_contains($files['login'], 'aria-describedby="login-security-note"') && str_contains($files['login'], '5 tentativi') && str_contains($files['login'], 'un’ora'), 'aiuto login'],
    [str_contains($files['login'], 'value="{{ error ? \'\' : last_username }}"'), 'identificativo login non ristampato dopo errore'],
    [str_contains($files['css'], ':focus-visible') && str_contains($files['css'], 'prefers-reduced-motion: reduce'), 'focus e movimento ridotto'],
    [str_contains($files['validation'], 'scripts/m92h-accessibility-security-contract.php') && str_contains($files['validation'], 'M9.2-H VALIDATION PASSED'), 'gate M9.2-H'],
    [str_contains($files['packager'], 'StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip'), 'package M9.2-H'],
];

foreach (['docs/USER_MANUAL.md', 'docs/ADMIN_MANUAL.md', 'docs/SECURITY.md', 'docs/ACCESSIBILITY.md'] as $document) {
    $checks[] = [is_file($root.'/'.$document), 'documento '.$document];
}

foreach ($checks as [$condition, $label]) {
    if (!$condition) {
        fwrite(STDERR, "Contratto M9.2-H non rispettato: {$label}.\n");
        exit(1);
    }
}

fwrite(STDOUT, "M9.2-H accessibility/security contract passed.\n");
