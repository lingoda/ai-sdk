<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\RateLimit;

use Lingoda\AiSdk\ModelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Interface for external rate limiter implementations that can be injected into the SDK
 * from framework-specific code (e.g., Symfony Bundle).
 */
interface ExternalRateLimiterInterface
{
    /**
     * Create or retrieve a rate limiter for the given provider and type.
     * 
     * @param string $providerId The AI provider ID (e.g., 'openai', 'anthropic')
     * @param string $type The limiter type ('requests' or 'tokens')
     * @param ModelInterface $model The model being used (for context)
     *
     * @return RateLimiterFactory
     */
    public function getRateLimiter(string $providerId, string $type, ModelInterface $model): RateLimiterFactoryInterface;
    
    /**
     * Check if external rate limiting is available for the given provider.
     */
    public function hasRateLimiter(string $providerId, string $type): bool;
    
    /**
     * Get the configuration key for rate limiter identification.
     */
    public function getRateLimiterKey(string $providerId, string $type, ModelInterface $model): string;
}