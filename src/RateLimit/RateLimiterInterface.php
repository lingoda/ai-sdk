<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\RateLimit;

use Lingoda\AiSdk\Exception\RateLimitExceededException;
use Lingoda\AiSdk\ModelInterface;

interface RateLimiterInterface
{
    /**
     * Check and consume rate limit for the given model and estimated tokens.
     *
     * @throws RateLimitExceededException
     */
    public function consume(ModelInterface $model, int $estimatedTokens = 1): void;

    /**
     * Check if the rate limit would be exceeded for the given model and estimated tokens.
     */
    public function isAllowed(ModelInterface $model, int $estimatedTokens = 1): bool;

    /**
     * Get the time until the rate limit resets for the given model.
     */
    public function getRetryAfter(ModelInterface $model): ?int;
}