<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\RateLimit;

use Lingoda\AiSdk\ModelInterface;

interface TokenEstimatorInterface
{
    /**
     * Estimate the number of tokens for the given payload.
     *
     * @param array<string, mixed>|array<int, array{role: string, content: string}>|string $payload
     */
    public function estimate(ModelInterface $model, array|string $payload): int;
}