<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\RateLimit;

/**
 * System delay implementation that uses PHP's built-in sleep() function.
 */
final readonly class SystemDelay implements DelayInterface
{
    public function delay(int $seconds): void
    {
        sleep($seconds);
    }
}