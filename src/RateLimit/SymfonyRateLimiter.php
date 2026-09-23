<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\RateLimit;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\RateLimitExceededException;
use Lingoda\AiSdk\ModelInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class SymfonyRateLimiter implements RateLimiterInterface
{
    /**
     * @var array<string, array{requests: RateLimiterFactoryInterface, tokens: RateLimiterFactoryInterface}>
     */
    private array $limiters = [];

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?LockFactory $lockFactory = null,
        private readonly ?ExternalRateLimiterInterface $externalRateLimiter = null,
    ) {
    }

    public function consume(ModelInterface $model, int $estimatedTokens = 1): void
    {
        $limiters = $this->getLimitersForModel($model);

        try {
            $requestLimit = $limiters['requests']->create($this->getRequestKey($model))->consume();
            if (!$requestLimit->isAccepted()) {
                $retryAfter = $requestLimit->getRetryAfter()->getTimestamp() - time();
                throw new RateLimitExceededException($retryAfter, 'Request rate limit exceeded');
            }

            $tokenLimit = $limiters['tokens']->create($this->getTokenKey($model))->consume($estimatedTokens);
            if (!$tokenLimit->isAccepted()) {
                $retryAfter = $tokenLimit->getRetryAfter()->getTimestamp() - time();
                throw new RateLimitExceededException($retryAfter, 'Token rate limit exceeded');
            }

            $this->logger->debug('Rate limit check passed', [
                'model' => $model->getId(),
                'provider' => $model->getProvider()->getId(),
                'estimated_tokens' => $estimatedTokens,
            ]);
        } catch (RateLimitExceededException $e) {
            $this->logger->warning('Rate limit exceeded', [
                'model' => $model->getId(),
                'provider' => $model->getProvider()->getId(),
                'estimated_tokens' => $estimatedTokens,
                'retry_after' => $e->getRetryAfter(),
            ]);
            throw $e;
        }
    }

    public function isAllowed(ModelInterface $model, int $estimatedTokens = 1): bool
    {
        try {
            $this->consume($model, $estimatedTokens);
            return true;
        } catch (RateLimitExceededException) {
            return false;
        }
    }

    /**
     * Seconds until one more request and one more token are available, null when available now.
     */
    public function getRetryAfter(ModelInterface $model): ?int
    {
        $limiters = $this->getLimitersForModel($model);

        // consume(0) reads the limiter state without taking anything
        $retryAt = max(
            $limiters['requests']->create($this->getRequestKey($model))->consume(0)->getRetryAfter()->getTimestamp(),
            $limiters['tokens']->create($this->getTokenKey($model))->consume(0)->getRetryAfter()->getTimestamp(),
        );
        $wait = $retryAt - time();

        return $wait > 0 ? $wait : null;
    }

    /**
     * @return array{requests: RateLimiterFactoryInterface, tokens: RateLimiterFactoryInterface}
     */
    private function getLimitersForModel(ModelInterface $model): array
    {
        $providerId = $model->getProvider()->getId();

        return $this->limiters[$providerId] ??= $this->createLimitersForProvider($providerId, $model);
    }

    /**
     * External limiters (e.g. from the Symfony bundle) win per type; missing ones fall back to internal defaults.
     *
     * @return array{requests: RateLimiterFactoryInterface, tokens: RateLimiterFactoryInterface}
     */
    private function createLimitersForProvider(string $providerId, ModelInterface $model): array
    {
        $external = $this->externalRateLimiter;
        $hasExternalRequests = $external?->hasRateLimiter($providerId, 'requests') ?? false;
        $hasExternalTokens = $external?->hasRateLimiter($providerId, 'tokens') ?? false;

        $this->logger->debug('Rate limiters created for provider', [
            'provider' => $providerId,
            'model' => $model->getId(),
            'external_requests' => $hasExternalRequests,
            'external_tokens' => $hasExternalTokens,
        ]);

        return [
            'requests' => $external !== null && $hasExternalRequests
                ? $external->getRateLimiter($providerId, 'requests', $model)
                : $this->createInternalRequestLimiter($providerId),
            'tokens' => $external !== null && $hasExternalTokens
                ? $external->getRateLimiter($providerId, 'tokens', $model)
                : $this->createInternalTokenLimiter($providerId),
        ];
    }

    private function createInternalRequestLimiter(string $providerId): RateLimiterFactory
    {
        $storage = new InMemoryStorage();
        $lockFactory = $this->lockFactory ?? new LockFactory(new InMemoryStore());
        $limits = $this->getProviderLimits($providerId);

        return new RateLimiterFactory([
            'id' => $providerId . '_requests',
            'policy' => 'token_bucket',
            'limit' => $limits['requests']['limit'],
            'rate' => $limits['requests']['rate'],
        ], $storage, $lockFactory);
    }

    private function createInternalTokenLimiter(string $providerId): RateLimiterFactory
    {
        $storage = new InMemoryStorage();
        $lockFactory = $this->lockFactory ?? new LockFactory(new InMemoryStore());
        $limits = $this->getProviderLimits($providerId);

        return new RateLimiterFactory([
            'id' => $providerId . '_tokens',
            'policy' => 'token_bucket',
            'limit' => $limits['tokens']['limit'],
            'rate' => $limits['tokens']['rate'],
        ], $storage, $lockFactory);
    }

    /**
     * @return array{requests: array{limit: int, rate: array{interval: string, amount: int}}, tokens: array{limit: int, rate: array{interval: string, amount: int}}}
     */
    private function getProviderLimits(string $providerId): array
    {
        // Provider defaults live on AIProvider; unknown provider ids get a conservative limit
        $defaults = AIProvider::tryFrom($providerId)?->getDefaultRateLimits()
            ?? ['requests_per_minute' => 60, 'tokens_per_minute' => 50000];

        return [
            'requests' => [
                'limit' => $defaults['requests_per_minute'],
                'rate' => ['interval' => '1 minute', 'amount' => $defaults['requests_per_minute']],
            ],
            'tokens' => [
                'limit' => $defaults['tokens_per_minute'],
                'rate' => ['interval' => '1 minute', 'amount' => $defaults['tokens_per_minute']],
            ],
        ];
    }

    private function getRequestKey(ModelInterface $model): string
    {
        return sprintf('%s_requests', $model->getProvider()->getId());
    }

    private function getTokenKey(ModelInterface $model): string
    {
        return sprintf('%s_tokens', $model->getProvider()->getId());
    }
}
