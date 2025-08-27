<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Exception;


final class RateLimitExceededException extends RuntimeException
{
    public function __construct(
        private readonly int $retryAfter,
        string $message = 'Rate limit exceeded',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}