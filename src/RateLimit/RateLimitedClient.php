<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\RateLimit;

use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Exception\RateLimitExceededException;
use Lingoda\AiSdk\Exception\RuntimeException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\ResultInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class RateLimitedClient implements ClientInterface
{
    private const int MAX_RETRIES = 10;
    private const int BASE_RETRY_DELAY = 1; // seconds
    
    private DelayInterface $delay;

    public function __construct(
        private ClientInterface $client,
        private RateLimiterInterface $rateLimiter,
        private TokenEstimatorRegistry $estimatorRegistry,
        private LoggerInterface $logger = new NullLogger(),
        ?DelayInterface $delay = null,
        private bool $enableRetries = true,
        private int $maxRetries = self::MAX_RETRIES,
    ) {
        $this->delay = $delay ?? new SystemDelay();
    }

    public function supports(ModelInterface $model): bool
    {
        return $this->client->supports($model);
    }

    /**
     * @throws \Throwable
     */
    public function request(ModelInterface $model, array|string $payload, array $options = []): ResultInterface
    {
        $estimatedTokens = $this->estimatorRegistry->estimate($model, $payload);

        $maxAttempts = $this->enableRetries ? $this->maxRetries : 1;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                // Check rate limits before making the request
                $this->rateLimiter->consume($model, $estimatedTokens);

                return $this->client->request($model, $payload, $options);
            } catch (RateLimitExceededException $e) {
                if (!$this->enableRetries) {
                    // If retries are disabled, throw immediately
                    throw $e;
                }
                $this->handleRateLimit($e, $attempt);
            } catch (\Throwable $e) {
                $this->logger->error('Request failed', [
                    'provider' => $model->getProvider()->getId(),
                    'model' => $model->getId(),
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);

                if (!$this->enableRetries) {
                    // If retries are disabled, throw immediately
                    throw $e;
                }

                // Handle specific error cases
                if ($this->isRetryableError($e)) {
                    $delay = $this->calculateExponentialBackoff($attempt);
                    $this->logger->warning('Retryable error occurred. Retrying after delay.', [
                        'delay' => $delay,
                        'attempt' => $attempt + 1,
                        'exception' => $e,
                    ]);
                    $this->delay->delay($delay);
                } else {
                    // Non-retryable error, throw immediately
                    throw $e;
                }
            }
        }

        throw new RuntimeException('Failed to execute request after maximum retries');
    }

    public function getProvider(): ProviderInterface
    {
        return $this->client->getProvider();
    }

    /**
     * @throws RateLimitExceededException
     */
    private function handleRateLimit(RateLimitExceededException $e, int $attempt): void
    {
        if ($attempt >= self::MAX_RETRIES - 1) {
            throw $e;
        }

        $retryAfter = max($e->getRetryAfter(), 0);
        
        $this->logger->warning('Rate limit exceeded. Waiting before retry.', [
            'retry_after' => $retryAfter,
            'attempt' => $attempt + 1,
        ]);

        if ($retryAfter > 0) {
            $this->delay->delay($retryAfter);
        }
    }

    private function isRetryableError(\Throwable $e): bool
    {
        $message = $e->getMessage();
        
        // Common retryable error patterns
        $retryablePatterns = [
            'timeout',
            'connection',
            'network',
            'temporary',
            'service unavailable',
            'too many requests',
            'rate limit',
            'idle timeout',
        ];

        foreach ($retryablePatterns as $pattern) {
            if (mb_stripos($message, $pattern) !== false) {
                return true;
            }
        }

        // HTTP 5xx errors are typically retryable
        return $e instanceof \RuntimeException && preg_match('/API returned status (5\d{2})/', $message);
    }

    private function calculateExponentialBackoff(int $attempt): int
    {
        return min(30, self::BASE_RETRY_DELAY * (2 ** $attempt)); // Cap at 30 seconds
    }
}
