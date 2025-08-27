<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\RateLimit;

/**
 * Interface for delaying execution.
 * Allows for testable delay operations following Symfony Clock patterns.
 */
interface DelayInterface
{
    /**
     * Delay execution for the specified number of seconds.
     */
    public function delay(int $seconds): void;
}