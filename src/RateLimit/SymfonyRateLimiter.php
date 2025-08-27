<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\RateLimit;

use Lingoda\AiSdk\Exception\RateLimitExceededException;
use Lingoda\AiSdk\ModelInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class SymfonyRateLimiter implements RateLimiterInterface
{
    /**
     * @var array<string, array{requests: ?RateLimiterFactory, tokens: ?RateLimiterFactory}>
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
            // Check request rate limit if available
            if ($limiters['requests'] !== null) {
                $requestLimiter = $limiters['requests']->create($this->getRequestKey($model));
                $requestLimit = $requestLimiter->consume();
                
                if (!$requestLimit->isAccepted()) {
                    $retryAfter = $requestLimit->getRetryAfter()->getTimestamp() - time();
                    throw new RateLimitExceededException($retryAfter, 'Request rate limit exceeded');
                }
            }

            // Check token rate limit if available
            if ($limiters['tokens'] !== null) {
                $tokenLimiter = $limiters['tokens']->create($this->getTokenKey($model));
                $tokenLimit = $tokenLimiter->consume($estimatedTokens);
                
                if (!$tokenLimit->isAccepted()) {
                    $retryAfter = $tokenLimit->getRetryAfter()->getTimestamp() - time();
                    throw new RateLimitExceededException($retryAfter, 'Token rate limit exceeded');
                }
            }

            $this->logger->debug('Rate limit check passed', [
                'model' => $model->getId(),
                'provider' => $model->getProvider()->getId(),
                'estimated_tokens' => $estimatedTokens,
                'requests_limiter' => $limiters['requests'] !== null ? 'enabled' : 'disabled',
                'tokens_limiter' => $limiters['tokens'] !== null ? 'enabled' : 'disabled',
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

    public function getRetryAfter(ModelInterface $model): ?int
    {
        $limiters = $this->getLimitersForModel($model);
        
        $waitTimes = [];
        
        // Check request limiter if available
        if ($limiters['requests'] !== null) {
            $requestLimiter = $limiters['requests']->create($this->getRequestKey($model));
            $requestReservation = $requestLimiter->reserve(1);
            $waitTimes[] = $requestReservation->getWaitDuration();
        }
        
        // Check token limiter if available
        if ($limiters['tokens'] !== null) {
            $tokenLimiter = $limiters['tokens']->create($this->getTokenKey($model));
            $tokenReservation = $tokenLimiter->reserve(1);
            $waitTimes[] = $tokenReservation->getWaitDuration();
        }
        
        // Return the longest wait time from available limiters
        if (empty($waitTimes)) {
            return null;
        }
        
        $maxRetryAfter = max($waitTimes);
        
        return $maxRetryAfter > 0 ? (int)$maxRetryAfter : null;
    }

    /**
     * @return array{requests: ?RateLimiterFactory, tokens: ?RateLimiterFactory}
     */
    private function getLimitersForModel(ModelInterface $model): array
    {
        $providerId = $model->getProvider()->getId();
        
        if (!isset($this->limiters[$providerId])) {
            $this->limiters[$providerId] = $this->createLimitersForProvider($providerId, $model);
        }
        
        return $this->limiters[$providerId];
    }

    /**
     * @return array{requests: ?RateLimiterFactory, tokens: ?RateLimiterFactory}
     */
    private function createLimitersForProvider(string $providerId, ModelInterface $model): array
    {
        $limiters = ['requests' => null, 'tokens' => null];
        
        // Try to use external rate limiters first (e.g., from Symfony Bundle)
        if ($this->externalRateLimiter !== null) {
            $hasExternalRequests = $this->externalRateLimiter->hasRateLimiter($providerId, 'requests');
            $hasExternalTokens = $this->externalRateLimiter->hasRateLimiter($providerId, 'tokens');
            
            if ($hasExternalRequests || $hasExternalTokens) {
                // Use external limiters where available
                if ($hasExternalRequests) {
                    $limiters['requests'] = $this->externalRateLimiter->getRateLimiter($providerId, 'requests', $model);
                }
                if ($hasExternalTokens) {
                    $limiters['tokens'] = $this->externalRateLimiter->getRateLimiter($providerId, 'tokens', $model);
                }
                
                // Fill in missing limiters with internal defaults
                if ($limiters['requests'] === null) {
                    $limiters['requests'] = $this->createInternalRequestLimiter($providerId);
                }
                if ($limiters['tokens'] === null) {
                    $limiters['tokens'] = $this->createInternalTokenLimiter($providerId);
                }
                
                $this->logger->debug('Using mixed external/internal rate limiters for provider', [
                    'provider' => $providerId,
                    'model' => $model->getId(),
                    'external_requests' => $hasExternalRequests,
                    'external_tokens' => $hasExternalTokens,
                ]);
                
                return $limiters;
            }
        }
        
        // Fallback to default internal rate limiters
        $this->logger->debug('Using internal rate limiters for provider', [
            'provider' => $providerId,
            'model' => $model->getId(),
        ]);
        
        return $this->createInternalLimitersForProvider($providerId);
    }

    /**
     * @return array{requests: RateLimiterFactory, tokens: RateLimiterFactory}
     */
    private function createInternalLimitersForProvider(string $providerId): array
    {
        return [
            'requests' => $this->createInternalRequestLimiter($providerId),
            'tokens' => $this->createInternalTokenLimiter($providerId),
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
        return match ($providerId) {
            'openai' => [
                'requests' => [
                    'limit' => 180, // Conservative 90% of 200 RPM
                    'rate' => ['interval' => '1 minute', 'amount' => 180]
                ],
                'tokens' => [
                    'limit' => 450000, // Conservative 90% of 500,000 TPM
                    'rate' => ['interval' => '1 minute', 'amount' => 450000]
                ]
            ],
            'anthropic' => [
                'requests' => [
                    'limit' => 100,
                    'rate' => ['interval' => '1 minute', 'amount' => 100]
                ],
                'tokens' => [
                    'limit' => 100000,
                    'rate' => ['interval' => '1 minute', 'amount' => 100000]
                ]
            ],
            'gemini' => [
                'requests' => [
                    'limit' => 1000, // Gemini 2.5 Flash Tier 1
                    'rate' => ['interval' => '1 minute', 'amount' => 1000]
                ],
                'tokens' => [
                    'limit' => 1000000, // Gemini 2.5 Flash Tier 1
                    'rate' => ['interval' => '1 minute', 'amount' => 1000000]
                ]
            ],
            default => [
                'requests' => [
                    'limit' => 60, // Conservative default
                    'rate' => ['interval' => '1 minute', 'amount' => 60]
                ],
                'tokens' => [
                    'limit' => 50000, // Conservative default
                    'rate' => ['interval' => '1 minute', 'amount' => 50000]
                ]
            ]
        };
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