<?php

declare(strict_types=1);

namespace App\Security;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class LoginRateLimiter extends AbstractRequestRateLimiter implements EventSubscriberInterface
{
    private readonly RateLimiterFactory $local;
    private readonly RateLimiterFactory $global;

    public function __construct(
        #[Autowire(service: 'cache.rate_limiter')] CacheItemPoolInterface $cache,
        #[Autowire('%app.login_max_attempts%')] int $maxAttempts,
        #[Autowire('%app.login_lockout_interval%')] string $interval,
        #[Autowire('%kernel.secret%')] private readonly string $secret,
    ) {
        $storage = new CacheStorage($cache);
        $this->local = new RateLimiterFactory([
            'id' => 'studio_login_user_ip',
            'policy' => 'fixed_window',
            'limit' => $maxAttempts,
            'interval' => $interval,
        ], $storage);
        $this->global = new RateLimiterFactory([
            'id' => 'studio_login_ip',
            'policy' => 'fixed_window',
            'limit' => 5 * $maxAttempts,
            'interval' => $interval,
        ], $storage);
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'resetAfterLogin'];
    }

    public function resetAfterLogin(LoginSuccessEvent $event): void
    {
        if ('main' === $event->getFirewallName()) {
            // Successful authentication clears this account's counter, preserving the IP-wide limit.
            $this->localLimiter($event->getRequest())->reset();
        }
    }

    protected function getLimiters(Request $request): array
    {
        return [
            $this->global->create($this->key($request->getClientIp() ?? '')),
            $this->localLimiter($request),
        ];
    }

    private function localLimiter(Request $request): LimiterInterface
    {
        $identifier = $request->attributes->get(SecurityRequestAttributes::LAST_USERNAME, '');
        $identifier = is_string($identifier) ? mb_strtolower(trim($identifier), 'UTF-8') : '';

        return $this->local->create($this->key($identifier."\0".($request->getClientIp() ?? '')));
    }

    private function key(string $value): string
    {
        return hash_hmac('sha256', $value, $this->secret);
    }
}
