<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\RateLimit;

use Lingoda\AiSdk\Decision\DecisionPlatformInterface;
use Lingoda\AiSdk\Decision\DecisionResult;
use Lingoda\AiSdk\Decision\Question;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\ModelNotFoundException;
use Lingoda\AiSdk\Exception\RateLimitExceededException;
use Lingoda\AiSdk\ProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Rate limits a decision platform: each decide() takes one request and the estimated input tokens from the limiter.
 *
 * Waits when the local limiter is exhausted, and backs off exponentially when the provider answers that it is
 * throttled or overloaded (429, 529) or a gateway fails (502, 503, 504). Any other failure is rethrown at once.
 */
final readonly class RateLimitedDecisionPlatform implements DecisionPlatformInterface
{
    private const int MAX_RETRIES = 10;
    private const int BASE_RETRY_DELAY = 1; // seconds
    private const int MAX_RETRY_DELAY = 30; // seconds
    private const array RETRYABLE_STATUS_CODES = [429, 502, 503, 504, 529];

    private DelayInterface $delay;

    public function __construct(
        private DecisionPlatformInterface $platform,
        private RateLimiterInterface $rateLimiter,
        private TokenEstimatorRegistry $estimatorRegistry,
        private LoggerInterface $logger = new NullLogger(),
        ?DelayInterface $delay = null,
        private bool $enableRetries = true,
        private int $maxRetries = self::MAX_RETRIES,
    ) {
        $this->delay = $delay ?? new SystemDelay();
    }

    /**
     * @throws ModelNotFoundException when the model is not one of the provider's models
     * @throws InvalidArgumentException when no questions are given
     * @throws ClientException when the request fails, after the retries for throttling and gateway errors
     * @throws RateLimitExceededException when the local limiter stays exhausted, or retries are disabled
     */
    public function decide(string|array $state, array $questions, ?string $model = null): DecisionResult
    {
        // Unknown model ids fail before anything is taken from the limiter
        $provider = $this->platform->getProvider();
        $resolvedModel = $provider->getModel($model ?? $provider->getDefaultModel());
        $estimatedTokens = $this->estimatorRegistry->estimate($resolvedModel, $this->estimationPayload($state, $questions));
        $maxAttempts = $this->enableRetries ? max(1, $this->maxRetries) : 1;

        for ($attempt = 1; ; ++$attempt) {
            try {
                $this->rateLimiter->consume($resolvedModel, $estimatedTokens);

                return $this->platform->decide($state, $questions, $resolvedModel->getId());
            } catch (RateLimitExceededException $e) {
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
                $wait = max($e->getRetryAfter(), 0);
            } catch (ClientException $e) {
                if ($attempt >= $maxAttempts || !in_array($e->getCode(), self::RETRYABLE_STATUS_CODES, true)) {
                    throw $e;
                }
                $wait = min(self::MAX_RETRY_DELAY, self::BASE_RETRY_DELAY * (2 ** ($attempt - 1)));
            }

            $this->logger->warning('Decision rate limited, retrying', [
                'provider' => $provider->getId(),
                'model' => $resolvedModel->getId(),
                'attempt' => $attempt,
                'wait_seconds' => $wait,
                'reason' => $e::class,
                'code' => $e->getCode(),
            ]);

            if ($wait > 0) {
                $this->delay->delay($wait);
            }
        }
    }

    public function getProvider(): ProviderInterface
    {
        return $this->platform->getProvider();
    }

    /**
     * The text the provider reads: the state and every question.
     *
     * @param string|array<string, mixed> $state
     * @param array<string, Question> $questions
     */
    private function estimationPayload(string|array $state, array $questions): string
    {
        return (string) json_encode([
            'state' => $state,
            'questions' => array_map(static fn (Question $question): array => $question->toArray(), $questions),
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
